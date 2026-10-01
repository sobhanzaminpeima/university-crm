<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Document;
use App\Models\Student;
use App\Models\University;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\RateLimiter;

class PortalController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);
        $rateKey = 'portal-api-login:'.sha1(mb_strtolower($data['email']).'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            return response()->json(['message' => 'Too many login attempts', 'retry_after' => RateLimiter::availableIn($rateKey)], 429);
        }
        $user = User::query()->where('email', $data['email'])->where('role_slug', 'student')->whereNull('deleted_at')->first();
        if (!$user || !$user->is_active || !Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($rateKey, 300);
            return response()->json(['message' => 'Invalid credentials'], 401);
        }
        RateLimiter::clear($rateKey);
        if (!class_exists(\App\Support\AuthUser::class)) {
            return response()->json(['message' => 'Auth token service missing'], 500);
        }
        return response()->json(['token' => \App\Support\AuthUser::tokenFor($user)]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $user = $this->authUser($request);
        if ($user->role_slug !== 'student') {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $student = Student::query()
            ->where('tenant_id', $user->tenant_id)
            ->when(Schema::hasColumn('students', 'user_id'), function ($query) use ($user) {
                $query->where(function ($sub) use ($user) {
                    $sub->where('user_id', $user->id)->orWhere('email', $user->email);
                });
            }, fn ($query) => $query->where('email', $user->email))
            ->first();
        if (!$student) {
            return response()->json(['message' => 'Student profile not found'], 404);
        }

        $applications = Application::query()->where('tenant_id', $user->tenant_id)->where('student_id', $student->id)->get();
        $documents = Document::query()->where('tenant_id', $user->tenant_id)->where('student_id', $student->id)->get();

        return response()->json([
            'student' => $student,
            'applications' => $applications,
            'documents' => $documents,
        ]);
    }

    public function universities(Request $request): JsonResponse
    {
        $user = $this->authUser($request);
        if ($user->role_slug !== 'student') {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $universities = University::query()->where('tenant_id', $user->tenant_id)->where('is_active', 1)->limit(100)->get();
        return response()->json($universities);
    }

    public function uploadDocument(Request $request): JsonResponse
    {
        $user = $this->authUser($request);
        if ($user->role_slug !== 'student') {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $student = Student::query()
            ->where('tenant_id', $user->tenant_id)
            ->when(Schema::hasColumn('students', 'user_id'), function ($query) use ($user) {
                $query->where(function ($sub) use ($user) {
                    $sub->where('user_id', $user->id)->orWhere('email', $user->email);
                });
            }, fn ($query) => $query->where('email', $user->email))
            ->firstOrFail();
        $data = $request->validate([
            'type' => 'required|string|in:passport,diploma,transcript,english_certificate,photo,other_documents,payment_receipt',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $path = $request->file('file')->store('docs', 'public');
        $document = Document::query()->create([
            'tenant_id' => $user->tenant_id,
            'student_id' => $student->id,
            'type' => $data['type'],
            'file_url' => '/storage/'.$path,
            'file_name' => basename($path),
            'status' => 'uploaded',
        ]);

        return response()->json(['message' => 'Uploaded', 'document' => $document]);
    }
}
