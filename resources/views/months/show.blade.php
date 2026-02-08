<x-app-layout :mobile-title="$month->name" :mobile-back="route('months.index')">
    @php
        $board = $boards[0] ?? null;
        $prevMonth = $board['prevMonth'] ?? null;
        $nextMonth = $board['nextMonth'] ?? null;
    @endphp
    @php
        $fmt = fn ($value) => new \Illuminate\Support\HtmlString(
            number_format((float) $value, 2, '.', "'")
        );
    @endphp
    <div
        id="month-swipe-root"
        class="py-6 overflow-x-hidden"
        @if ($prevMonth) data-prev-url="{{ route('months.show', $prevMonth) }}" @endif
        @if ($nextMonth) data-next-url="{{ route('months.show', $nextMonth) }}" @endif
    >
        <div class="w-full px-[5px] sm:px-6 lg:px-10 space-y-4">
            @if ($errors->any())
                <div class="border border-red-200 bg-red-50 text-red-800 p-3 text-sm accent-box">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="space-y-6">
                @foreach ($boards as $board)
                    @include('months.partials.board', $board)
                @endforeach
            </div>
        </div>
    </div>

    <style>
        #month-swipe-root.is-swiping {
            transition: transform 180ms ease, opacity 180ms ease;
        }

        #month-swipe-root.is-swiping[data-swipe="next"] {
            transform: translateX(-12%);
            opacity: 0.7;
        }

        #month-swipe-root.is-swiping[data-swipe="prev"] {
            transform: translateX(12%);
            opacity: 0.7;
        }

        #month-swipe-overlay {
            transition: opacity 180ms ease;
        }

        #month-swipe-overlay.is-visible {
            opacity: 1;
        }
    </style>

    <div id="month-swipe-overlay" class="fixed inset-0 z-[980] pointer-events-none opacity-0">
        <div class="absolute inset-0 bg-[var(--surface)]/70 backdrop-blur-sm"></div>
        <div class="absolute inset-0 flex items-center justify-center">
            <div id="month-swipe-label" class="rounded-full border border-[var(--border)] bg-white/90 px-4 py-2 text-sm font-semibold text-gray-700 dark:bg-slate-900/90 dark:text-slate-100 shadow-sm">
                Monat wechseln
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

            const setupSortable = (tbody) => {
                let draggingRow = null;
                const orderUrl = tbody.dataset.orderUrl;
                const type = tbody.dataset.type;

                const persistOrder = () => {
                    const ids = Array.from(tbody.querySelectorAll('[data-entry-id]')).map((row) => row.dataset.entryId);
                    if (!ids.length || !orderUrl || !csrfToken) {
                        return;
                    }

                    fetch(orderUrl, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ entry_ids: ids, type }),
                    }).catch(() => {});
                };

                tbody.addEventListener('dragstart', (event) => {
                    const row = event.target.closest('[data-entry-id]');
                    if (!row) {
                        return;
                    }

                    draggingRow = row;
                    row.classList.add('opacity-50');
                    event.dataTransfer.effectAllowed = 'move';
                });

                tbody.addEventListener('dragend', () => {
                    if (draggingRow) {
                        draggingRow.classList.remove('opacity-50');
                    }
                    draggingRow = null;
                });

                tbody.addEventListener('dragover', (event) => {
                    if (!draggingRow) {
                        return;
                    }
                    event.preventDefault();

                    const row = event.target.closest('[data-entry-id]');
                    if (!row || row === draggingRow) {
                        return;
                    }

                    const rect = row.getBoundingClientRect();
                    const after = (event.clientY - rect.top) > rect.height / 2;
                    if (after) {
                        row.after(draggingRow);
                    } else {
                        row.before(draggingRow);
                    }
                });

                tbody.addEventListener('drop', (event) => {
                    if (!draggingRow) {
                        return;
                    }
                    event.preventDefault();
                    persistOrder();
                });
            };

            document.querySelectorAll('[data-sortable]').forEach(setupSortable);

            const swipeRoot = document.getElementById('month-swipe-root');
            const swipeOverlay = document.getElementById('month-swipe-overlay');
            const swipeLabel = document.getElementById('month-swipe-label');
            if (swipeRoot) {
                const prevUrl = swipeRoot.dataset.prevUrl;
                const nextUrl = swipeRoot.dataset.nextUrl;
                let startX = 0;
                let startY = 0;
                let startTime = 0;
                let tracking = false;

                const isInteractive = (target) =>
                    target?.closest?.('input, textarea, select, button, a, [contenteditable="true"]');

                swipeRoot.addEventListener('touchstart', (event) => {
                    if (event.touches.length !== 1 || isInteractive(event.target)) {
                        return;
                    }
                    const touch = event.touches[0];
                    startX = touch.clientX;
                    startY = touch.clientY;
                    startTime = Date.now();
                    tracking = true;
                }, { passive: true });

                swipeRoot.addEventListener('touchmove', (event) => {
                    if (!tracking || event.touches.length !== 1) {
                        return;
                    }
                    const touch = event.touches[0];
                    const dx = touch.clientX - startX;
                    const dy = touch.clientY - startY;
                    if (Math.abs(dy) > 50 && Math.abs(dy) > Math.abs(dx)) {
                        tracking = false;
                    }
                }, { passive: true });

                swipeRoot.addEventListener('touchend', (event) => {
                    if (!tracking || !event.changedTouches.length) {
                        tracking = false;
                        return;
                    }
                    const touch = event.changedTouches[0];
                    const dx = touch.clientX - startX;
                    const dy = touch.clientY - startY;
                    const elapsed = Date.now() - startTime;
                    tracking = false;

                    if (elapsed > 800 || Math.abs(dx) < 70 || Math.abs(dx) < Math.abs(dy) * 1.3) {
                        return;
                    }

                    const showTransition = (direction, label, url) => {
                        if (!url) {
                            return;
                        }
                        swipeRoot.dataset.swipe = direction;
                        swipeRoot.classList.add('is-swiping');
                        if (swipeLabel) {
                            swipeLabel.textContent = label;
                        }
                        if (swipeOverlay) {
                            swipeOverlay.classList.add('is-visible');
                        }
                        setTimeout(() => {
                            window.location = url;
                        }, 160);
                    };

                    if (dx > 0 && prevUrl) {
                        showTransition('prev', 'Vorheriger Monat', prevUrl);
                    } else if (dx < 0 && nextUrl) {
                        showTransition('next', 'Nächster Monat', nextUrl);
                    }
                }, { passive: true });
            }
        });
    </script>
</x-app-layout>
