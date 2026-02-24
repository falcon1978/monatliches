@php
    $openAmount = $income->open_amount;
    $displayAmount = $income->status === 'paid' ? (float) $income->amount : $openAmount;
@endphp

@php
    $carryoverLabel = $income->originMonth?->name ?? $income->movedFromMonth?->name;
@endphp

<div class="rounded-2xl border border-[var(--border)] bg-green-50/70 dark:bg-emerald-950/30 shadow-sm p-2.5">
    <div class="flex items-center justify-between gap-2">
        <div class="min-w-0 flex items-center gap-2">
            <a href="{{ route('entries.edit', $income) }}" class="touch-target inline-flex items-center justify-center rounded-full text-gray-400 hover:text-gray-700" aria-label="Bearbeiten">
                <x-icon-edit class="h-4 w-4" />
            </a>
            @if (! empty($prevMonth) || ! empty($nextMonth))
                <div class="flex items-center gap-1 text-gray-400 shrink-0">
                    @if (! empty($prevMonth))
                        <form method="POST" action="{{ route('entries.move-prev-month', $income) }}" onsubmit="return confirm('Eintrag in den Vormonat verschieben?');">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="touch-target inline-flex items-center justify-center rounded-full hover:text-gray-700" aria-label="Zum Vormonat" title="Zum Vormonat">
                                <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M15 18l-6-6 6-6" />
                                </svg>
                            </button>
                        </form>
                    @endif
                    @if (! empty($nextMonth))
                        <form method="POST" action="{{ route('entries.move-next-month', $income) }}" onsubmit="return confirm('Eintrag in den nächsten Monat verschieben?');">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="touch-target inline-flex items-center justify-center rounded-full hover:text-gray-700" aria-label="Zum nächsten Monat" title="Zum nächsten Monat">
                                <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M9 18l6-6-6-6" />
                                </svg>
                            </button>
                        </form>
                    @endif
                </div>
            @endif
            <div class="min-w-0 flex-1">
                <div class="text-sm font-semibold text-gray-900 dark:text-slate-100 truncate">{{ $income->description }}</div>
                @if ($carryoverLabel)
                    <div class="mt-1 text-[10px] font-semibold uppercase tracking-wide text-amber-800">Aus {{ $carryoverLabel }}</div>
                @endif
            </div>
        </div>
        <div class="flex items-center gap-1.5 shrink-0">
            <div class="text-sm font-semibold tabular-nums text-gray-900 dark:text-slate-100">CHF {{ $fmt($displayAmount) }}</div>
            @if (abs($openAmount) > 0.00001)
                <button type="button" class="touch-target inline-flex items-center justify-center rounded-full text-[var(--accent)]" aria-label="Zahlung eingegangen" title="Zahlung eingegangen" @click="sheet = 'payment'; paymentEntryId = {{ $income->id }}; paymentAmount = incomePaymentMap[{{ $income->id }}]">
                    <x-icon-check class="h-4 w-4" />
                </button>
            @endif
        </div>
    </div>
</div>
