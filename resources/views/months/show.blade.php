<x-app-layout>
    @php
        $fmt = fn ($value) => new \Illuminate\Support\HtmlString(
            number_format((float) $value, 2, '.', "'")
        );
    @endphp
    <div class="py-6">
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
        });
    </script>
</x-app-layout>
