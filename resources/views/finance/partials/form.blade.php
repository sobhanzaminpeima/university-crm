<div class="grid-4">
    @if($payment)
        <input type="hidden" name="student_id" value="{{ $payment->student_id }}">
    @else
        <select name="student_id" required>
            <option value="">Student</option>
            @foreach($students as $student)
                <option value="{{ $student->id }}">{{ $student->full_name }}</option>
            @endforeach
        </select>
    @endif
    <input name="type" placeholder="Type (tuition/deposit/...)" value="{{ old('type', $payment?->type) }}" required>
    <select name="currency" required>
        @foreach(['USD','EUR','GBP','TRY'] as $cur)
            <option value="{{ $cur }}" {{ old('currency', $payment?->currency ?? 'USD') === $cur ? 'selected' : '' }}>{{ $cur }}</option>
        @endforeach
    </select>
    <input name="amount" type="number" min="0" step="0.01" value="{{ old('amount', $payment?->amount) }}" required>

    <input name="commission_rate" type="number" min="0" max="100" step="0.01" value="{{ old('commission_rate', $payment?->commission_rate) }}" placeholder="Commission %">
    <select name="status" required>
        @foreach(['pending','paid','failed','refunded'] as $s)
            <option value="{{ $s }}" {{ old('status', $payment?->status ?? 'pending') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
        @endforeach
    </select>
    <input name="paid_at" type="datetime-local" value="{{ old('paid_at', $payment?->paid_at ? \Illuminate\Support\Carbon::parse($payment->paid_at)->format('Y-m-d\TH:i') : '') }}">
</div>
