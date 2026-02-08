<div class="hidden sm:block relative z-[900] border-b accent-box bg-white/80 dark:bg-slate-900/80 backdrop-blur">
    <div class="w-full px-4 sm:px-6 lg:px-10 py-2">
        <div class="flex items-center gap-3">
            <div class="w-full overflow-x-auto">
                <div class="flex items-center gap-2 whitespace-nowrap py-1">
                    @foreach ($monthBand['months'] as $bandMonth)
                        @php
                            $isPrimary = ($monthBand['currentMonthId'] ?? null) === $bandMonth->id;
                            $chipClass = $isPrimary
                                ? 'bg-[var(--accent)] text-white shadow-sm ring-1 ring-[var(--accent)]'
                                : 'bg-white/70 dark:bg-slate-900/70 text-gray-700 dark:text-slate-200 ring-1 ring-black/5 dark:ring-white/10 hover:opacity-90 dark:hover:bg-slate-800/80';

                            $title = $bandMonth->date_from->format('d.m.Y') . ' - ' . $bandMonth->date_to->format('d.m.Y');
                        @endphp

                        <a
                            href="{{ route('months.show', $bandMonth) }}"
                            class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold transition {{ $chipClass }}"
                            title="{{ $title }}"
                            @if ($isPrimary) aria-current="true" @endif
                        >
                            <span>{{ $bandMonth->name }}</span>
                            @if ($isPrimary)
                                <span class="h-1.5 w-1.5 rounded-full bg-white/90"></span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
