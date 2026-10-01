<?php

namespace App\Services;

use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Student;
use App\Models\StudentMessage;
use App\Models\StudentRequest;
use App\Models\Task;
use App\Models\TelegramLink;
use App\Models\TelegramLinkCode;
use App\Models\TelegramState;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Carbon;

class TelegramBotService
{
    private ?string $token;

    public function __construct()
    {
        $this->token = config('services.telegram.bot_token');
    }

    public function isConfigured(): bool
    {
        return !empty($this->token);
    }

    public function sendMessage(string $chatId, string $text, array $extra = []): void
    {
        if (!$this->isConfigured()) {
            return;
        }
        try {
            Http::asForm()->post("https://api.telegram.org/bot{$this->token}/sendMessage", array_merge([
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ], $extra));
        } catch (\Throwable $e) {
            Log::warning('Telegram sendMessage failed: '.$e->getMessage());
        }
    }

    public function registerCommands(): void
    {
        if (!$this->isConfigured()) {
            return;
        }
        $commands = [
            ['command' => 'menu', 'description' => 'نمایش منو'],
            ['command' => 'students', 'description' => 'دانشجویان اخیر'],
            ['command' => 'applications', 'description' => 'اپلیکیشن‌های اخیر'],
            ['command' => 'tasks', 'description' => 'تسک‌های باز من'],
            ['command' => 'deadlines', 'description' => 'ددلاین‌های پیش‌رو'],
            ['command' => 'newlead', 'description' => 'ثبت دانشجوی جدید'],
            ['command' => 'newtask', 'description' => 'ساخت تسک جدید'],
            ['command' => 'requests', 'description' => 'درخواست‌های در انتظار پورتال'],
            ['command' => 'myapplications', 'description' => '(دانشجو) اپلیکیشن‌های من'],
            ['command' => 'mydocuments', 'description' => '(دانشجو) مدارک من'],
            ['command' => 'mytasks', 'description' => '(دانشجو) تسک‌های من'],
            ['command' => 'uploaddoc', 'description' => '(دانشجو) آپلود مدرک'],
            ['command' => 'sendmessage', 'description' => '(دانشجو) پیام به تیم CRM'],
            ['command' => 'inbox', 'description' => 'مشاهده و پاسخ به پیام دانشجویان'],
            ['command' => 'search', 'description' => 'جستجوی دانشجو با نام/ایمیل/تلفن'],
            ['command' => 'movestage', 'description' => 'تغییر وضعیت اپلیکیشن: /movestage شناسه'],
            ['command' => 'profile', 'description' => 'پروفایل من'],
            ['command' => 'link', 'description' => 'اتصال حساب CRM'],
            ['command' => 'unlink', 'description' => 'قطع اتصال حساب'],
            ['command' => 'cancel', 'description' => 'لغو عملیات جاری'],
        ];
        try {
            Http::asForm()->post("https://api.telegram.org/bot{$this->token}/setMyCommands", [
                'commands' => json_encode($commands),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Telegram setMyCommands failed: '.$e->getMessage());
        }
    }

    private function inlineMenuFor(User $user): string
    {
        if ($user->role_slug === 'student') {
            $rows = [
                [['text' => '📄 اپلیکیشن‌های من', 'callback_data' => 'act:myapplications'], ['text' => '📎 مدارک من', 'callback_data' => 'act:mydocuments']],
                [['text' => '📤 آپلود مدرک', 'callback_data' => 'act:uploaddoc'], ['text' => '💬 پیام به تیم', 'callback_data' => 'act:sendmessage']],
                [['text' => '✅ تسک‌های من', 'callback_data' => 'act:mytasks'], ['text' => '👤 پروفایل', 'callback_data' => 'act:profile']],
            ];
        } else {
            $rows = [
                [['text' => '🎓 دانشجویان', 'callback_data' => 'act:students'], ['text' => '📄 اپلیکیشن‌ها', 'callback_data' => 'act:applications']],
                [['text' => '✅ تسک‌ها', 'callback_data' => 'act:tasks'], ['text' => '⏰ ددلاین‌ها', 'callback_data' => 'act:deadlines']],
                [['text' => '➕ لید جدید', 'callback_data' => 'act:newlead'], ['text' => '✅ تسک جدید', 'callback_data' => 'act:newtask']],
                [['text' => '💬 صندوق پیام', 'callback_data' => 'act:inbox']],
            ];
            $lastRow = [['text' => '👤 پروفایل', 'callback_data' => 'act:profile']];
            if ($user->hasPermission('student_requests.view')) {
                array_unshift($lastRow, ['text' => '📥 درخواست‌ها', 'callback_data' => 'act:requests']);
            }
            $rows[] = $lastRow;
        }

        return json_encode(['inline_keyboard' => $rows]);
    }

    private function answerCallbackQuery(string $callbackId): void
    {
        if (!$this->isConfigured()) {
            return;
        }
        try {
            Http::asForm()->post("https://api.telegram.org/bot{$this->token}/answerCallbackQuery", [
                'callback_query_id' => $callbackId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Telegram answerCallbackQuery failed: '.$e->getMessage());
        }
    }

    public function notifyUser(int $userId, string $text): void
    {
        $link = TelegramLink::query()->where('user_id', $userId)->first();
        if (!$link) {
            return;
        }
        $user = User::query()->find($userId);
        if (!$user || !$this->canUseTelegram($user)) {
            return;
        }
        $this->sendMessage($link->chat_id, $text);
    }

    public function generateLinkCode(User $user): string
    {
        TelegramLinkCode::query()->where('user_id', $user->id)->whereNull('used_at')->delete();
        $code = (string) random_int(100000, 999999);
        TelegramLinkCode::query()->create([
            'user_id' => $user->id,
            'code' => $code,
            'expires_at' => now()->addMinutes(10),
        ]);
        return $code;
    }

    public function getUpdates(int $offset): array
    {
        if (!$this->isConfigured()) {
            return [];
        }
        $response = Http::timeout(35)->get("https://api.telegram.org/bot{$this->token}/getUpdates", [
            'offset' => $offset,
            'timeout' => 30,
        ]);
        return $response->json('result') ?? [];
    }

    public function handleUpdate(array $update): void
    {
        if (isset($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query']);
            return;
        }

        $message = $update['message'] ?? null;
        if (!$message) {
            return;
        }
        $chatId = (string) $message['chat']['id'];

        if (isset($message['photo']) || isset($message['document'])) {
            $this->handleIncomingFile($chatId, $message);
            return;
        }

        if (empty($message['text'])) {
            return;
        }
        $text = trim((string) $message['text']);
        $telegramUsername = $message['from']['username'] ?? null;

        $link = TelegramLink::query()->where('chat_id', $chatId)->first();
        $user = $link ? User::query()->find($link->user_id) : null;

        [$command, $arg] = array_pad(explode(' ', $text, 2), 2, '');
        $command = strtolower(trim($command));
        $arg = trim($arg);

        if ($command === '/start') {
            $this->handleStart($chatId, $user);
            return;
        }
        if ($command === '/link') {
            $this->handleLink($chatId, $arg, $telegramUsername);
            return;
        }
        if (!$user) {
            $this->sendMessage($chatId, "هنوز اکانتت وصل نشده. برو توی CRM (یا پورتال دانشجویی) ← تنظیمات ← تلگرام، یک کد بساز و بفرست:\n<code>/link کد_شما</code>");
            return;
        }
        if ($command === '/unlink') {
            TelegramLink::query()->where('chat_id', $chatId)->delete();
            TelegramState::query()->where('chat_id', $chatId)->delete();
            $this->auditBotAction($user, 'telegram.unlink', 'user', $user->id);
            $this->sendMessage($chatId, '✅ اتصال تلگرامت قطع شد.');
            return;
        }
        if (!$this->canUseTelegram($user)) {
            $this->sendMessage($chatId, '⛔ دسترسی بات تلگرام برای حساب شما غیرفعال است. با مدیر CRM تماس بگیرید یا /unlink را بفرستید.');
            return;
        }

        $state = TelegramState::query()->where('chat_id', $chatId)->first();
        if ($state && $command === '/cancel') {
            $state->delete();
            $this->sendMessage($chatId, 'لغو شد.');
            return;
        }
        if ($state && !str_starts_with($text, '/')) {
            $this->continueFlow($chatId, $user, $state, $text);
            return;
        }

        $this->dispatchCommand($chatId, $user, $command, $arg);
    }

    private function handleCallbackQuery(array $callbackQuery): void
    {
        $callbackId = (string) ($callbackQuery['id'] ?? '');
        $chatId = (string) ($callbackQuery['message']['chat']['id'] ?? '');
        $data = (string) ($callbackQuery['data'] ?? '');
        if ($callbackId !== '') {
            $this->answerCallbackQuery($callbackId);
        }
        if ($chatId === '') {
            return;
        }

        $link = TelegramLink::query()->where('chat_id', $chatId)->first();
        $user = $link ? User::query()->find($link->user_id) : null;
        if (!$user) {
            $this->sendMessage($chatId, 'هنوز وصل نیستی. اول این رو بفرست: /link کد_شما');
            return;
        }
        if (!$this->canUseTelegram($user)) {
            $this->sendMessage($chatId, '⛔ دسترسی بات تلگرام برای حساب شما غیرفعال است.');
            return;
        }

        if (str_starts_with($data, 'doctype:')) {
            $this->chooseDocType($chatId, $user, substr($data, 8));
            return;
        }

        if (str_starts_with($data, 'chat:')) {
            $this->startReplyFlow($chatId, $user, (int) substr($data, 5));
            return;
        }

        if (str_starts_with($data, 'pg:')) {
            $parts = explode(':', $data, 3);
            $this->handlePage($chatId, $user, $parts[1] ?? '', (int) ($parts[2] ?? 0));
            return;
        }

        if (str_starts_with($data, 'stage:')) {
            $parts = explode(':', $data, 3);
            $this->setApplicationStage($chatId, $user, (int) ($parts[1] ?? 0), (string) ($parts[2] ?? ''));
            return;
        }

        if (!str_starts_with($data, 'act:')) {
            return;
        }

        TelegramState::query()->where('chat_id', $chatId)->delete();
        $command = '/'.substr($data, 4);
        $this->dispatchCommand($chatId, $user, $command, '');
    }

    private function handlePage(string $chatId, User $user, string $type, int $offset): void
    {
        match ($type) {
            'students' => $this->handleStudents($chatId, $user, $offset),
            'applications' => $this->handleApplications($chatId, $user, $offset),
            default => null,
        };
    }

    private function dispatchCommand(string $chatId, User $user, string $command, string $arg): void
    {
        match ($command) {
            '/menu' => $this->handleMenu($chatId, $user),
            '/profile' => $this->handleProfile($chatId, $user),
            '/students' => $this->handleStudents($chatId, $user),
            '/applications' => $this->handleApplications($chatId, $user),
            '/tasks' => $this->handleTasks($chatId, $user),
            '/deadlines' => $this->handleDeadlines($chatId, $user),
            '/myapplications' => $this->handleMyApplications($chatId, $user),
            '/mydocuments' => $this->handleMyDocuments($chatId, $user),
            '/mytasks' => $this->handleMyTasks($chatId, $user),
            '/newlead' => $this->startNewLeadFlow($chatId, $user),
            '/newtask' => $this->startNewTaskFlow($chatId, $user),
            '/requests' => $this->handleRequests($chatId, $user),
            '/approve' => $this->handleApproveRequest($chatId, $user, $arg),
            '/reject' => $this->handleRejectRequest($chatId, $user, $arg),
            '/uploaddoc' => $this->startUploadDocFlow($chatId, $user),
            '/sendmessage' => $this->startSendMessageFlow($chatId, $user),
            '/inbox' => $this->handleInbox($chatId, $user),
            '/search' => $this->handleSearch($chatId, $user, $arg),
            '/movestage' => $this->startMoveStage($chatId, $user, $arg),
            default => $this->handleMenu($chatId, $user),
        };
    }

    private function handleStart(string $chatId, ?User $user): void
    {
        if ($user) {
            $this->handleMenu($chatId, $user);
            return;
        }
        $this->sendMessage($chatId, "👋 به بات Vertue CRM خوش اومدی.\n\nبرای اتصال حسابت، برو توی CRM (تنظیمات ← تلگرام) یا پورتال دانشجویی (تنظیمات) و یک کد یک‌بارمصرف بساز، بعد بفرست:\n<code>/link کد_شما</code>");
    }

    private function handleLink(string $chatId, string $code, ?string $telegramUsername): void
    {
        if ($code === '') {
            $this->sendMessage($chatId, 'استفاده: /link کد_شما');
            return;
        }
        $chatRateKey = 'telegram-link:chat:'.hash('sha256', $chatId);
        $codeRateKey = 'telegram-link:code:'.hash('sha256', $code);
        if (RateLimiter::tooManyAttempts($chatRateKey, 5) || RateLimiter::tooManyAttempts($codeRateKey, 10)) {
            $seconds = max(
                RateLimiter::availableIn($chatRateKey),
                RateLimiter::availableIn($codeRateKey)
            );
            $minutes = max(1, (int) ceil($seconds / 60));
            $this->sendMessage($chatId, "⏳ تلاش‌های زیادی انجام شده. لطفاً حدود {$minutes} دقیقه دیگر دوباره امتحان کن.");
            return;
        }
        $record = TelegramLinkCode::query()
            ->where('code', $code)
            ->whereNull('used_at')
            ->where('expires_at', '>=', now())
            ->latest('id')
            ->first();
        if (!$record) {
            RateLimiter::hit($chatRateKey, 600);
            RateLimiter::hit($codeRateKey, 600);
            $this->sendMessage($chatId, '❌ کد اشتباه یا منقضی‌شده. از تنظیمات یک کد جدید بساز و دوباره امتحان کن.');
            return;
        }
        $user = User::query()->find($record->user_id);
        if (!$user || !$this->canUseTelegram($user)) {
            $this->sendMessage($chatId, '❌ حساب فعال نیست یا دسترسی بات تلگرام برای آن صادر نشده است.');
            return;
        }
        TelegramLink::query()->where('user_id', $user->id)->delete();
        TelegramLink::query()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'chat_id' => $chatId,
            'telegram_username' => $telegramUsername,
            'linked_at' => now(),
        ]);
        $record->update(['used_at' => now()]);
        RateLimiter::clear($chatRateKey);
        RateLimiter::clear($codeRateKey);
        $this->auditBotAction($user, 'telegram.link', 'user', $user->id, ['telegram_username' => $telegramUsername]);

        $this->sendMessage($chatId, "✅ با موفقیت وصل شدی: <b>{$user->name}</b> ({$this->roleLabel($user->role_slug)}).");
        $this->handleMenu($chatId, $user);
    }

    private function handleMenu(string $chatId, User $user): void
    {
        $keyboard = $this->inlineMenuFor($user);
        $title = $user->role_slug === 'student'
            ? '📋 <b>منوی دانشجو</b>'
            : "📋 <b>منو</b> ({$this->roleLabel($user->role_slug)})";
        $this->sendMessage($chatId, "{$title}\nیکی از دکمه‌ها رو بزن، یا برای لغو هر عملیات در حال انجام /cancel رو بفرست.", [
            'reply_markup' => $keyboard,
        ]);
    }

    private function handleProfile(string $chatId, User $user): void
    {
        $this->sendMessage($chatId, "<b>{$user->name}</b>\nنقش: {$this->roleLabel($user->role_slug)}\nایمیل: {$user->email}");
    }

    private const PAGE_SIZE = 10;

    private function handleStudents(string $chatId, User $user, int $offset = 0): void
    {
        if (in_array($user->role_slug, ['student'], true)) {
            $this->sendMessage($chatId, 'این دستور برای نقش شما در دسترس نیست. /menu رو امتحان کن.');
            return;
        }
        $query = Student::query()->forTenant($user->tenant_id, $user->role_slug)->whereNull('deleted_at');
        if (in_array($user->role_slug, ['agent', 'sub_agent'], true) && !$user->hasPermission('students.view_all')) {
            $query->when($user->role_slug === 'agent', fn ($q) => $q->where('agent_id', $user->id))
                ->when($user->role_slug === 'sub_agent', fn ($q) => $q->where('sub_agent_id', $user->id));
        }
        $students = $query->latest('id')->skip($offset)->limit(self::PAGE_SIZE + 1)->get(['id', 'full_name', 'stage', 'email']);
        if ($students->isEmpty()) {
            $this->sendMessage($chatId, $offset > 0 ? 'دانشجوی بیشتری نیست.' : 'دانشجویی پیدا نشد.');
            return;
        }
        $hasMore = $students->count() > self::PAGE_SIZE;
        $students = $students->take(self::PAGE_SIZE);
        $lines = [$offset > 0 ? '👥 <b>دانشجویان (ادامه)</b>' : '👥 <b>دانشجویان اخیر</b>'];
        foreach ($students as $s) {
            $lines[] = "#{$s->id} {$s->full_name} — ".$this->stageLabel($s->stage);
        }
        $extra = $hasMore ? ['reply_markup' => json_encode(['inline_keyboard' => [[['text' => '▶️ بیشتر', 'callback_data' => 'pg:students:'.($offset + self::PAGE_SIZE)]]]])] : [];
        $this->sendMessage($chatId, implode("\n", $lines), $extra);
    }

    private const APPLICATION_STATUSES = [
        'new_lead', 'interested', 'application_started', 'documents_pending',
        'interview_scheduled', 'offer_sent', 'visa_process', 'tuition_paid', 'enrolled', 'rejected',
    ];

    private function startMoveStage(string $chatId, User $user, string $arg): void
    {
        if (!$user->hasPermission('applications.update')) {
            $this->sendMessage($chatId, 'شما اجازه‌ی ویرایش اپلیکیشن‌ها رو ندارید.');
            return;
        }
        $appId = (int) trim($arg);
        if ($appId <= 0) {
            $this->sendMessage($chatId, 'استفاده: /movestage شناسه_اپلیکیشن (شناسه‌ها رو از /applications ببین)');
            return;
        }
        $application = Application::query()->forTenant($user->tenant_id, $user->role_slug)->find($appId);
        if (!$application) {
            $this->sendMessage($chatId, "اپلیکیشن #{$appId} پیدا نشد.");
            return;
        }
        $buttons = [];
        $row = [];
        foreach (self::APPLICATION_STATUSES as $status) {
            $label = $this->statusLabel($status);
            $row[] = ['text' => $label, 'callback_data' => "stage:{$appId}:{$status}"];
            if (count($row) === 2) {
                $buttons[] = $row;
                $row = [];
            }
        }
        if ($row) {
            $buttons[] = $row;
        }
        $this->sendMessage($chatId, "اپلیکیشن #{$appId} — وضعیت فعلی: <b>".$this->statusLabel($application->status)."</b>\nوضعیت جدید رو انتخاب کن:", [
            'reply_markup' => json_encode(['inline_keyboard' => $buttons]),
        ]);
    }

    private function setApplicationStage(string $chatId, User $user, int $appId, string $status): void
    {
        if (!$user->hasPermission('applications.update')) {
            $this->sendMessage($chatId, 'شما اجازه‌ی ویرایش اپلیکیشن‌ها رو ندارید.');
            return;
        }
        if (!in_array($status, self::APPLICATION_STATUSES, true)) {
            $this->sendMessage($chatId, 'وضعیت نامعتبر.');
            return;
        }
        $application = Application::query()->forTenant($user->tenant_id, $user->role_slug)->find($appId);
        if (!$application) {
            $this->sendMessage($chatId, "اپلیکیشن #{$appId} پیدا نشد.");
            return;
        }
        $oldStatus = $application->status;
        $application->update(['status' => $status]);
        $this->auditBotAction($user, 'application.update', 'application', $application->id, ['status' => $status, 'previous_status' => $oldStatus]);
        $this->sendMessage($chatId, "✅ اپلیکیشن #{$appId} به <b>".$this->statusLabel($status)."</b> تغییر کرد.");
    }

    private function handleSearch(string $chatId, User $user, string $query): void
    {
        if ($user->role_slug === 'student') {
            $this->sendMessage($chatId, 'این دستور برای نقش شما در دسترس نیست.');
            return;
        }
        $query = trim($query);
        if ($query === '') {
            $this->sendMessage($chatId, 'استفاده: /search نام، ایمیل یا تلفن');
            return;
        }
        $students = Student::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->whereNull('deleted_at')
            ->when(in_array($user->role_slug, ['agent', 'sub_agent'], true) && !$user->hasPermission('students.view_all'), function ($q) use ($user) {
                $q->when($user->role_slug === 'agent', fn ($q2) => $q2->where('agent_id', $user->id))
                    ->when($user->role_slug === 'sub_agent', fn ($q2) => $q2->where('sub_agent_id', $user->id));
            })
            ->where(function ($q) use ($query) {
                $q->where('full_name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%");
            })
            ->limit(10)
            ->get(['id', 'full_name', 'stage', 'email']);

        if ($students->isEmpty()) {
            $this->sendMessage($chatId, "دانشجویی با \"{$query}\" پیدا نشد.");
            return;
        }
        $lines = ["🔎 <b>جستجو: \"{$query}\"</b>"];
        foreach ($students as $s) {
            $lines[] = "#{$s->id} {$s->full_name} ({$s->email}) — ".$this->stageLabel($s->stage);
        }
        $this->sendMessage($chatId, implode("\n", $lines));
    }

    private function handleApplications(string $chatId, User $user, int $offset = 0): void
    {
        if ($user->role_slug === 'student') {
            $this->sendMessage($chatId, '/myapplications رو امتحان کن.');
            return;
        }
        $query = Application::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->leftJoin('students', 'students.id', '=', 'applications.student_id')
            ->leftJoin('universities', 'universities.id', '=', 'applications.university_id')
            ->select(['applications.*', 'students.full_name as student_name', 'universities.name as university_name']);
        if (in_array($user->role_slug, ['agent', 'sub_agent'], true) && !$user->hasPermission('applications.view_all')) {
            $query->whereIn('applications.student_id', function ($sub) use ($user) {
                $sub->select('id')->from('students')->where('tenant_id', $user->tenant_id)
                    ->when($user->role_slug === 'agent', fn ($q) => $q->where('agent_id', $user->id))
                    ->when($user->role_slug === 'sub_agent', fn ($q) => $q->where('sub_agent_id', $user->id));
            });
        }
        $apps = $query->latest('applications.id')->skip($offset)->limit(self::PAGE_SIZE + 1)->get();
        if ($apps->isEmpty()) {
            $this->sendMessage($chatId, $offset > 0 ? 'اپلیکیشن بیشتری نیست.' : 'اپلیکیشنی پیدا نشد.');
            return;
        }
        $hasMore = $apps->count() > self::PAGE_SIZE;
        $apps = $apps->take(self::PAGE_SIZE);
        $lines = [$offset > 0 ? '📄 <b>اپلیکیشن‌ها (ادامه)</b>' : '📄 <b>اپلیکیشن‌های اخیر</b>'];
        foreach ($apps as $a) {
            $lines[] = "#{$a->id} {$a->student_name} ← {$a->university_name} — ".$this->statusLabel($a->status);
        }
        $extra = $hasMore ? ['reply_markup' => json_encode(['inline_keyboard' => [[['text' => '▶️ بیشتر', 'callback_data' => 'pg:applications:'.($offset + self::PAGE_SIZE)]]]])] : [];
        $this->sendMessage($chatId, implode("\n", $lines), $extra);
    }

    private function handleTasks(string $chatId, User $user): void
    {
        $tasks = Task::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->where('assigned_to', $user->id)
            ->whereIn('status', ['todo', 'in_progress', 'blocked'])
            ->orderBy('deadline')
            ->limit(10)
            ->get(['id', 'title', 'status', 'deadline']);
        if ($tasks->isEmpty()) {
            $this->sendMessage($chatId, '✅ هیچ تسک بازی به شما اختصاص داده نشده.');
            return;
        }
        $lines = ['✅ <b>تسک‌های باز شما</b>'];
        foreach ($tasks as $t) {
            $due = $t->deadline ? Carbon::parse($t->deadline)->format('Y-m-d') : 'بدون ددلاین';
            $lines[] = "#{$t->id} {$t->title} — ددلاین {$due}";
        }
        $this->sendMessage($chatId, implode("\n", $lines));
    }

    private function handleDeadlines(string $chatId, User $user): void
    {
        if ($user->role_slug === 'student') {
            $this->sendMessage($chatId, '/myapplications رو امتحان کن.');
            return;
        }
        $query = Application::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->leftJoin('students', 'students.id', '=', 'applications.student_id')
            ->select(['applications.*', 'students.full_name as student_name'])
            ->whereNotNull('applications.deadline')
            ->whereNotIn('applications.status', ['enrolled', 'rejected']);
        if (in_array($user->role_slug, ['agent', 'sub_agent'], true) && !$user->hasPermission('applications.view_all')) {
            $query->whereIn('applications.student_id', function ($sub) use ($user) {
                $sub->select('id')->from('students')->where('tenant_id', $user->tenant_id)
                    ->when($user->role_slug === 'agent', fn ($q) => $q->where('agent_id', $user->id))
                    ->when($user->role_slug === 'sub_agent', fn ($q) => $q->where('sub_agent_id', $user->id));
            });
        }
        $apps = $query->orderBy('applications.deadline')->limit(10)->get();
        if ($apps->isEmpty()) {
            $this->sendMessage($chatId, 'ددلاین نزدیکی وجود نداره.');
            return;
        }
        $lines = ['⏰ <b>ددلاین‌های پیش‌رو</b>'];
        foreach ($apps as $a) {
            $days = Carbon::today()->diffInDays(Carbon::parse($a->deadline), false);
            $tag = $days < 0 ? (abs($days).' روز گذشته') : ($days.' روز مونده');
            $lines[] = "{$a->deadline} — {$a->student_name} (#{$a->id}) — {$tag}";
        }
        $this->sendMessage($chatId, implode("\n", $lines));
    }

    private function handleMyApplications(string $chatId, User $user): void
    {
        $student = Student::query()->where('user_id', $user->id)->first();
        if (!$student) {
            $this->sendMessage($chatId, 'هیچ پروفایل دانشجویی به این حساب وصل نیست.');
            return;
        }
        $apps = Application::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('student_id', $student->id)
            ->leftJoin('universities', 'universities.id', '=', 'applications.university_id')
            ->select(['applications.*', 'universities.name as university_name'])
            ->latest('applications.id')
            ->get();
        if ($apps->isEmpty()) {
            $this->sendMessage($chatId, 'هنوز هیچ اپلیکیشنی نداری.');
            return;
        }
        $lines = ['📄 <b>اپلیکیشن‌های من</b>'];
        foreach ($apps as $a) {
            $lines[] = "{$a->university_name} — {$a->program} — ".$this->statusLabel($a->status);
        }
        $this->sendMessage($chatId, implode("\n", $lines));
    }

    private function handleMyDocuments(string $chatId, User $user): void
    {
        $student = Student::query()->where('user_id', $user->id)->first();
        if (!$student) {
            $this->sendMessage($chatId, 'هیچ پروفایل دانشجویی به این حساب وصل نیست.');
            return;
        }
        $docs = \App\Models\Document::query()->where('tenant_id', $user->tenant_id)->where('student_id', $student->id)->get(['type', 'status']);
        if ($docs->isEmpty()) {
            $this->sendMessage($chatId, 'هنوز مدرکی آپلود نشده.');
            return;
        }
        $lines = ['📎 <b>مدارک من</b>'];
        foreach ($docs as $d) {
            $lines[] = ($this::DOC_TYPE_LABELS[$d->type] ?? $d->type).' — '.$this->docStatusLabel($d->status);
        }
        $this->sendMessage($chatId, implode("\n", $lines));
    }

    private const DOC_TYPE_LABELS = [
        'passport' => 'پاسپورت',
        'diploma' => 'مدرک تحصیلی',
        'transcript' => 'ریزنمرات',
        'english_certificate' => 'مدرک زبان انگلیسی',
        'photo' => 'عکس',
        'payment_receipt' => 'رسید پرداخت',
        'other_documents' => 'سایر مدارک',
    ];

    private function startUploadDocFlow(string $chatId, User $user): void
    {
        if ($user->role_slug !== 'student') {
            $this->sendMessage($chatId, 'این دستور فقط برای دانشجوهاست.');
            return;
        }
        $student = Student::query()->where('user_id', $user->id)->first();
        if (!$student) {
            $this->sendMessage($chatId, 'هیچ پروفایل دانشجویی به این حساب وصل نیست.');
            return;
        }
        TelegramState::query()->updateOrCreate(
            ['chat_id' => $chatId],
            ['flow' => 'upload_doc', 'step' => 'choose_type', 'data_json' => '{}']
        );
        $buttons = [];
        $row = [];
        foreach (self::DOC_TYPE_LABELS as $type => $label) {
            $row[] = ['text' => $label, 'callback_data' => 'doctype:'.$type];
            if (count($row) === 2) {
                $buttons[] = $row;
                $row = [];
            }
        }
        if ($row) {
            $buttons[] = $row;
        }
        $this->sendMessage($chatId, '📤 کدوم مدرک رو می‌خوای آپلود کنی؟', [
            'reply_markup' => json_encode(['inline_keyboard' => $buttons]),
        ]);
    }

    private function chooseDocType(string $chatId, User $user, string $docType): void
    {
        if (!array_key_exists($docType, self::DOC_TYPE_LABELS)) {
            $this->sendMessage($chatId, 'نوع مدرک نامشخص.');
            return;
        }
        $state = TelegramState::query()->where('chat_id', $chatId)->first();
        if (!$state || $state->flow !== 'upload_doc') {
            $this->sendMessage($chatId, 'لطفاً دوباره با /uploaddoc شروع کن.');
            return;
        }
        $state->step = 'awaiting_file';
        $state->setData(['doc_type' => $docType]);
        $state->save();
        $label = self::DOC_TYPE_LABELS[$docType];
        $this->sendMessage($chatId, "📤 حالا <b>{$label}</b> رو به‌صورت عکس یا فایل بفرست (یا /cancel برای لغو).");
    }

    private function handleIncomingFile(string $chatId, array $message): void
    {
        $link = TelegramLink::query()->where('chat_id', $chatId)->first();
        $user = $link ? User::query()->find($link->user_id) : null;
        if (!$user) {
            $this->sendMessage($chatId, 'هنوز وصل نیستی. اول این رو بفرست: /link کد_شما');
            return;
        }
        if (!$this->canUseTelegram($user)) {
            $this->sendMessage($chatId, '⛔ دسترسی بات تلگرام برای حساب شما غیرفعال است.');
            return;
        }
        $state = TelegramState::query()->where('chat_id', $chatId)->first();
        if (!$state || $state->flow !== 'upload_doc' || $state->step !== 'awaiting_file') {
            $this->sendMessage($chatId, 'الان منتظر فایل نبودم. اول دکمه‌ی آپلود مدرک رو بزن.');
            return;
        }
        $docType = $state->data()['doc_type'] ?? null;
        if (!$docType) {
            $state->delete();
            $this->sendMessage($chatId, 'یه مشکلی پیش اومد، لطفاً دوباره با /uploaddoc امتحان کن.');
            return;
        }
        $student = Student::query()->where('user_id', $user->id)->first();
        if (!$student) {
            $state->delete();
            $this->sendMessage($chatId, 'هیچ پروفایل دانشجویی به این حساب وصل نیست.');
            return;
        }

        $fileId = null;
        $fileName = null;
        if (isset($message['document'])) {
            $fileId = $message['document']['file_id'];
            $fileName = $message['document']['file_name'] ?? ('document_'.time());
        } elseif (isset($message['photo'])) {
            $sizes = $message['photo'];
            $largest = end($sizes);
            $fileId = $largest['file_id'] ?? null;
            $fileName = 'photo_'.time().'.jpg';
        }
        if (!$fileId) {
            $this->sendMessage($chatId, 'لطفاً یک عکس یا فایل بفرست.');
            return;
        }

        $bytes = $this->downloadTelegramFile($fileId);
        if ($bytes === null) {
            $this->sendMessage($chatId, '❌ دانلود فایل از تلگرام ناموفق بود. دوباره امتحان کن.');
            return;
        }

        $extension = pathinfo((string) $fileName, PATHINFO_EXTENSION) ?: 'jpg';
        $storedName = 'docs/'.bin2hex(random_bytes(20)).'.'.$extension;
        \Illuminate\Support\Facades\Storage::disk('public')->put($storedName, $bytes);

        $payload = [
            'tenant_id' => $user->tenant_id,
            'student_id' => $student->id,
            'type' => $docType,
            'file_url' => '/storage/'.$storedName,
            'file_name' => $fileName,
            'status' => 'uploaded',
            'expiry_date' => null,
            'ocr_json' => null,
        ];

        $existing = \App\Models\Document::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('student_id', $student->id)
            ->where('type', $docType)
            ->latest('id')
            ->first();
        if ($existing) {
            $existing->update($payload);
            $document = $existing;
        } else {
            $document = \App\Models\Document::query()->create($payload);
        }

        $state->delete();
        $this->auditBotAction($user, 'document.upload', 'document', $document->id, ['type' => $docType, 'via' => 'telegram']);
        $this->sendMessage($chatId, '✅ مدرک آپلود شد: <b>'.self::DOC_TYPE_LABELS[$docType].'</b>. تیم ما به‌زودی بررسیش می‌کنه.');
    }

    private function downloadTelegramFile(string $fileId): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }
        try {
            $fileInfo = Http::get("https://api.telegram.org/bot{$this->token}/getFile", ['file_id' => $fileId]);
            $filePath = $fileInfo->json('result.file_path');
            if (!$filePath) {
                return null;
            }
            $response = Http::get("https://api.telegram.org/file/bot{$this->token}/{$filePath}");
            if (!$response->successful()) {
                return null;
            }
            return $response->body();
        } catch (\Throwable $e) {
            Log::warning('Telegram file download failed: '.$e->getMessage());
            return null;
        }
    }

    private function handleMyTasks(string $chatId, User $user): void
    {
        $student = Student::query()->where('user_id', $user->id)->first();
        if (!$student) {
            $this->sendMessage($chatId, 'هیچ پروفایل دانشجویی به این حساب وصل نیست.');
            return;
        }
        $tasks = Task::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('student_id', $student->id)
            ->whereIn('status', ['todo', 'in_progress', 'blocked'])
            ->get(['title', 'status', 'deadline']);
        if ($tasks->isEmpty()) {
            $this->sendMessage($chatId, '✅ تسک باز نداری.');
            return;
        }
        $lines = ['✅ <b>تسک‌های باز من</b>'];
        foreach ($tasks as $t) {
            $due = $t->deadline ? Carbon::parse($t->deadline)->format('Y-m-d') : 'بدون ددلاین';
            $lines[] = "{$t->title} — ددلاین {$due}";
        }
        $this->sendMessage($chatId, implode("\n", $lines));
    }

    private function roleLabel(string $role): string
    {
        return match ($role) {
            'super_admin' => 'مدیر کل',
            'admin' => 'ادمین',
            'agent' => 'ایجنت',
            'sub_agent' => 'ساب‌ایجنت',
            'student' => 'دانشجو',
            default => ucwords(str_replace('_', ' ', $role)),
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'new_lead' => 'لید جدید',
            'interested' => 'علاقه‌مند',
            'application_started' => 'شروع اپلیکیشن',
            'documents_pending' => 'در انتظار مدارک',
            'interview_scheduled' => 'مصاحبه برنامه‌ریزی‌شده',
            'offer_sent' => 'آفر ارسال‌شده',
            'visa_process' => 'در حال ویزا',
            'tuition_paid' => 'شهریه پرداخت‌شده',
            'enrolled' => 'ثبت‌نام‌شده',
            'rejected' => 'رد شده',
            default => ucwords(str_replace('_', ' ', $status)),
        };
    }

    private function stageLabel(string $stage): string
    {
        return match ($stage) {
            'lead' => 'لید',
            'inquiry' => 'استعلام',
            'applicant' => 'متقاضی',
            'documents_pending' => 'در انتظار مدارک',
            'interview_scheduled' => 'مصاحبه برنامه‌ریزی‌شده',
            'admitted' => 'پذیرفته‌شده',
            'visa_process' => 'در حال ویزا',
            'tuition_paid' => 'شهریه پرداخت‌شده',
            'enrolled' => 'ثبت‌نام‌شده',
            'alumni' => 'فارغ‌التحصیل',
            default => ucwords(str_replace('_', ' ', $stage)),
        };
    }

    private function docStatusLabel(string $status): string
    {
        return match ($status) {
            'missing' => 'ثبت‌نشده',
            'uploaded' => 'آپلودشده',
            'verified' => 'تأییدشده',
            'rejected' => 'ردشده',
            default => ucwords($status),
        };
    }

    private function auditBotAction(User $user, string $action, string $entityType, int $entityId, array $diff = []): void
    {
        AuditLog::query()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'diff_json' => json_encode(array_merge($diff, ['via' => 'telegram']), JSON_UNESCAPED_UNICODE),
            'ip_address' => null,
        ]);
    }

    private function startNewLeadFlow(string $chatId, User $user): void
    {
        if ($user->role_slug === 'student' || !$user->hasPermission('students.create')) {
            $this->sendMessage($chatId, 'اجازه‌ی افزودن دانشجو رو نداری.');
            return;
        }
        TelegramState::query()->updateOrCreate(
            ['chat_id' => $chatId],
            ['flow' => 'new_lead', 'step' => 'name', 'data_json' => '{}']
        );
        $this->sendMessage($chatId, "➕ <b>لید دانشجوی جدید</b>\nنام کامل دانشجو رو بفرست (یا /cancel برای لغو):");
    }

    private function startNewTaskFlow(string $chatId, User $user): void
    {
        if (!$user->hasPermission('tasks.create')) {
            $this->sendMessage($chatId, 'اجازه‌ی ساخت تسک رو نداری.');
            return;
        }
        TelegramState::query()->updateOrCreate(
            ['chat_id' => $chatId],
            ['flow' => 'new_task', 'step' => 'title', 'data_json' => '{}']
        );
        $this->sendMessage($chatId, "✅ <b>تسک جدید</b>\nعنوان تسک رو بفرست (یا /cancel برای لغو):");
    }

    private function continueFlow(string $chatId, User $user, TelegramState $state, string $text): void
    {
        if ($state->flow === 'new_lead') {
            $this->continueNewLeadFlow($chatId, $user, $state, $text);
            return;
        }
        if ($state->flow === 'new_task') {
            $this->continueNewTaskFlow($chatId, $user, $state, $text);
            return;
        }
        if ($state->flow === 'upload_doc') {
            if ($state->step === 'choose_type') {
                $this->sendMessage($chatId, 'لطفاً یکی از دکمه‌های بالا رو برای انتخاب نوع مدرک بزن.');
            } else {
                $this->sendMessage($chatId, 'لطفاً عکس یا فایل بفرست، نه متن. یا /cancel برای لغو.');
            }
            return;
        }
        if ($state->flow === 'send_message') {
            $this->finishSendMessage($chatId, $user, $text);
            $state->delete();
            return;
        }
        if ($state->flow === 'reply_message') {
            $this->finishReplyMessage($chatId, $user, $state, $text);
            $state->delete();
            return;
        }
        $state->delete();
    }

    private function continueNewLeadFlow(string $chatId, User $user, TelegramState $state, string $text): void
    {
        $data = $state->data();

        if ($state->step === 'name') {
            $data['full_name'] = $text;
            $state->setData($data);
            $state->step = 'email';
            $state->save();
            $this->sendMessage($chatId, 'ایمیل این دانشجو رو بفرست:');
            return;
        }

        if ($state->step === 'email') {
            if (!filter_var($text, FILTER_VALIDATE_EMAIL)) {
                $this->sendMessage($chatId, 'این ایمیل معتبر به‌نظر نمی‌رسه. دوباره امتحان کن، یا /cancel:');
                return;
            }
            $exists = Student::query()->forTenant($user->tenant_id, $user->role_slug)
                ->whereNull('deleted_at')->where('email', $text)->exists();
            if ($exists) {
                $this->sendMessage($chatId, "دانشجویی با این ایمیل از قبل وجود داره. یک ایمیل دیگه بفرست، یا /cancel:");
                return;
            }
            $data['email'] = $text;
            $state->setData($data);
            $state->step = 'phone';
            $state->save();
            $this->sendMessage($chatId, "شماره تلفن؟ (یک شماره بفرست، یا 'skip' برای رد کردن)");
            return;
        }

        if ($state->step === 'phone') {
            $data['phone'] = strtolower($text) === 'skip' ? null : $text;
            $student = Student::query()->create([
                'tenant_id' => $user->tenant_id,
                'agent_id' => $user->role_slug === 'agent' ? $user->id : null,
                'sub_agent_id' => $user->role_slug === 'sub_agent' ? $user->id : null,
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'stage' => 'lead',
                'stage_temperature' => 'warm',
                'is_active' => 1,
            ]);
            $state->delete();
            $this->auditBotAction($user, 'student.create', 'student', $student->id, ['full_name' => $student->full_name]);
            $this->sendMessage($chatId, "✅ لید ساخته شد: <b>{$student->full_name}</b> (#{$student->id})");
        }
    }

    private function continueNewTaskFlow(string $chatId, User $user, TelegramState $state, string $text): void
    {
        $data = $state->data();

        if ($state->step === 'title') {
            $data['title'] = $text;
            $state->setData($data);
            $state->step = 'deadline';
            $state->save();
            $this->sendMessage($chatId, "تاریخ سررسید؟ (به فرم YYYY-MM-DD، یا 'skip')");
            return;
        }

        if ($state->step === 'deadline') {
            $deadline = null;
            if (strtolower($text) !== 'skip') {
                try {
                    $deadline = Carbon::parse($text);
                } catch (\Throwable) {
                    $this->sendMessage($chatId, "این تاریخ رو نفهمیدم. از فرمت YYYY-MM-DD استفاده کن، یا 'skip':");
                    return;
                }
            }
            $task = Task::query()->create([
                'tenant_id' => $user->tenant_id,
                'assigned_to' => $user->id,
                'title' => $data['title'],
                'status' => 'todo',
                'priority' => 'medium',
                'deadline' => $deadline,
            ]);
            $state->delete();
            $this->auditBotAction($user, 'task.create', 'task', $task->id, ['title' => $task->title]);
            $this->sendMessage($chatId, "✅ تسک ساخته شد: <b>{$task->title}</b> (#{$task->id})");
        }
    }

    private function startSendMessageFlow(string $chatId, User $user): void
    {
        if ($user->role_slug !== 'student') {
            $this->sendMessage($chatId, 'این دستور فقط برای دانشجوهاست.');
            return;
        }
        $student = Student::query()->where('user_id', $user->id)->first();
        if (!$student) {
            $this->sendMessage($chatId, 'هیچ پروفایل دانشجویی به این حساب وصل نیست.');
            return;
        }
        TelegramState::query()->updateOrCreate(
            ['chat_id' => $chatId],
            ['flow' => 'send_message', 'step' => 'awaiting_text', 'data_json' => '{}']
        );
        $this->sendMessage($chatId, '💬 پیامت رو برای تیم CRM بنویس (یا /cancel برای لغو):');
    }

    private function finishSendMessage(string $chatId, User $user, string $text): void
    {
        $student = Student::query()->where('user_id', $user->id)->first();
        if (!$student) {
            $this->sendMessage($chatId, 'هیچ پروفایل دانشجویی به این حساب وصل نیست.');
            return;
        }
        $recipient = User::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereIn('role_slug', ['admin', 'agent', 'super_admin'])
            ->where('is_active', 1)
            ->orderByRaw("FIELD(role_slug, 'agent', 'admin', 'super_admin')")
            ->first();

        $message = StudentMessage::query()->create([
            'tenant_id' => $user->tenant_id,
            'student_id' => $student->id,
            'student_user_id' => $user->id,
            'recipient_user_id' => $recipient?->id,
            'sender_role' => 'student',
            'body' => $text,
        ]);

        if ($recipient) {
            Notification::query()->create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $recipient->id,
                'type' => 'student_message',
                'title' => 'New message from student',
                'body' => $student->full_name.': '.$text,
                'meta_json' => json_encode(['message_id' => $message->id, 'student_id' => $student->id]),
            ]);
        }

        $this->auditBotAction($user, 'message.send', 'student_message', $message->id);
        $this->sendMessage($chatId, '✅ پیام برای تیم CRM ارسال شد.');
    }

    private function handleInbox(string $chatId, User $user): void
    {
        $students = Student::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->whereNull('deleted_at')
            ->when(in_array($user->role_slug, ['agent', 'sub_agent'], true) && !$user->hasPermission('students.view_all'), function ($query) use ($user) {
                $query->when($user->role_slug === 'agent', fn ($q) => $q->where('agent_id', $user->id))
                    ->when($user->role_slug === 'sub_agent', fn ($q) => $q->where('sub_agent_id', $user->id));
            })
            ->orderBy('full_name')
            ->limit(8)
            ->get(['id', 'full_name']);

        if ($students->isEmpty()) {
            $this->sendMessage($chatId, 'دانشجویی برای ارسال پیام پیدا نشد.');
            return;
        }

        $buttons = $students->map(fn ($s) => [['text' => $s->full_name, 'callback_data' => 'chat:'.$s->id]])->values()->all();
        $this->sendMessage($chatId, '💬 یک دانشجو رو برای دیدن/پاسخ به پیام‌ها انتخاب کن:', [
            'reply_markup' => json_encode(['inline_keyboard' => $buttons]),
        ]);
    }

    private function startReplyFlow(string $chatId, User $user, int $studentId): void
    {
        $student = Student::query()->forTenant($user->tenant_id, $user->role_slug)->whereNull('deleted_at')->find($studentId);
        if (!$student) {
            $this->sendMessage($chatId, 'دانشجو پیدا نشد.');
            return;
        }

        $recent = StudentMessage::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->where('student_id', $student->id)
            ->latest('id')
            ->limit(5)
            ->get()
            ->reverse();

        if ($recent->isEmpty()) {
            $this->sendMessage($chatId, "هنوز پیامی با <b>{$student->full_name}</b> رد و بدل نشده.");
        } else {
            $lines = ["💬 <b>پیام‌های اخیر با {$student->full_name}</b>"];
            foreach ($recent as $msg) {
                $from = $msg->sender_role === 'student' ? 'دانشجو' : 'تیم CRM';
                $lines[] = "{$from}: ".\Illuminate\Support\Str::limit($msg->body, 140);
            }
            $this->sendMessage($chatId, implode("\n", $lines));
        }

        TelegramState::query()->updateOrCreate(
            ['chat_id' => $chatId],
            ['flow' => 'reply_message', 'step' => 'awaiting_text', 'data_json' => json_encode(['student_id' => $student->id])]
        );
        $this->sendMessage($chatId, "پاسخت به <b>{$student->full_name}</b> رو بنویس (یا /cancel برای لغو):");
    }

    private function finishReplyMessage(string $chatId, User $user, TelegramState $state, string $text): void
    {
        $studentId = (int) ($state->data()['student_id'] ?? 0);
        $student = Student::query()->forTenant($user->tenant_id, $user->role_slug)->whereNull('deleted_at')->find($studentId);
        if (!$student) {
            $this->sendMessage($chatId, 'دانشجو پیدا نشد.');
            return;
        }
        $studentUser = null;
        if ($student->user_id) {
            $studentUser = User::query()->where('id', $student->user_id)->where('role_slug', 'student')->first();
        }
        if (!$studentUser && !empty($student->email)) {
            $studentUser = User::query()->where('email', $student->email)->where('role_slug', 'student')->first();
        }
        if (!$studentUser) {
            $this->sendMessage($chatId, 'حساب پورتال دانشجویی پیدا نشد.');
            return;
        }

        $message = StudentMessage::query()->create([
            'tenant_id' => $user->tenant_id,
            'student_id' => $student->id,
            'student_user_id' => $studentUser->id,
            'recipient_user_id' => $studentUser->id,
            'sender_role' => $user->role_slug,
            'body' => $text,
        ]);

        Notification::query()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $studentUser->id,
            'type' => 'admin_message',
            'title' => 'New message from CRM',
            'body' => $text,
            'meta_json' => json_encode(['message_id' => $message->id, 'student_id' => $student->id]),
        ]);

        $this->auditBotAction($user, 'message.send', 'student_message', $message->id, ['student_id' => $student->id]);
        $this->sendMessage($chatId, "✅ پیام برای <b>{$student->full_name}</b> ارسال شد.");
    }

    private function handleRequests(string $chatId, User $user): void
    {
        if (!$user->hasPermission('student_requests.view')) {
            $this->sendMessage($chatId, 'اجازه‌ی مشاهده‌ی درخواست‌های پورتال رو نداری.');
            return;
        }
        $requests = StudentRequest::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->where('status', 'pending')
            ->latest('id')
            ->limit(10)
            ->get(['id', 'full_name', 'email', 'target_program']);
        if ($requests->isEmpty()) {
            $this->sendMessage($chatId, 'درخواست در انتظاری وجود نداره.');
            return;
        }
        $lines = ['📥 <b>درخواست‌های در انتظار</b>'];
        foreach ($requests as $r) {
            $lines[] = "#{$r->id} {$r->full_name} ({$r->email}) — {$r->target_program}";
        }
        $lines[] = "\nبرای بررسی از /approve شناسه یا /reject شناسه استفاده کن.";
        $this->sendMessage($chatId, implode("\n", $lines));
    }

    private function handleApproveRequest(string $chatId, User $user, string $arg): void
    {
        if (!$user->hasPermission('student_requests.approve')) {
            $this->sendMessage($chatId, 'اجازه‌ی تأیید درخواست‌ها رو نداری.');
            return;
        }
        $id = (int) trim($arg);
        if ($id <= 0) {
            $this->sendMessage($chatId, 'استفاده: /approve شناسه');
            return;
        }
        $requestItem = StudentRequest::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->where('status', 'pending')
            ->find($id);
        if (!$requestItem) {
            $this->sendMessage($chatId, "درخواست #{$id} پیدا نشد یا قبلاً بررسی شده.");
            return;
        }
        $student = Student::query()->create([
            'tenant_id' => $user->tenant_id,
            'agent_id' => $user->role_slug === 'agent' ? $user->id : null,
            'sub_agent_id' => $user->role_slug === 'sub_agent' ? $user->id : null,
            'full_name' => $requestItem->full_name,
            'email' => $requestItem->email,
            'phone' => $requestItem->phone,
            'nationality' => $requestItem->nationality,
            'field_of_study' => $requestItem->target_program,
            'stage' => 'lead',
            'stage_temperature' => 'warm',
            'is_active' => 1,
        ]);
        $requestItem->update(['status' => 'approved', 'processed_by' => $user->id, 'processed_at' => now()]);
        $this->auditBotAction($user, 'student_request.approve', 'student_request', $requestItem->id, ['student_id' => $student->id]);
        $this->sendMessage($chatId, "✅ تأیید شد. دانشجو ساخته شد: <b>{$student->full_name}</b> (#{$student->id})");
    }

    private function handleRejectRequest(string $chatId, User $user, string $arg): void
    {
        if (!$user->hasPermission('student_requests.reject')) {
            $this->sendMessage($chatId, 'اجازه‌ی رد درخواست‌ها رو نداری.');
            return;
        }
        $id = (int) trim($arg);
        if ($id <= 0) {
            $this->sendMessage($chatId, 'استفاده: /reject شناسه');
            return;
        }
        $requestItem = StudentRequest::query()
            ->forTenant($user->tenant_id, $user->role_slug)
            ->where('status', 'pending')
            ->find($id);
        if (!$requestItem) {
            $this->sendMessage($chatId, "درخواست #{$id} پیدا نشد یا قبلاً بررسی شده.");
            return;
        }
        $requestItem->update(['status' => 'rejected', 'processed_by' => $user->id, 'processed_at' => now()]);
        $this->auditBotAction($user, 'student_request.reject', 'student_request', $requestItem->id);
        $this->sendMessage($chatId, "❌ درخواست #{$id} رد شد.");
    }

    private function canUseTelegram(User $user): bool
    {
        if (!(bool) $user->is_active || $user->deleted_at !== null) {
            return false;
        }

        return $user->role_slug === 'student' || $user->hasPermission('telegram.use');
    }
}
