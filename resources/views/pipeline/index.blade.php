@extends('layouts.app')

@section('content')
<div class="card">
    <h2 style="margin-top:0;">Pipeline Board</h2>
    <p class="footer-note">Drag a student card and drop it on another stage.</p>
    <div style="display:flex;gap:8px;align-items:center;margin-bottom:10px;">
        <label class="footer-note">Hide stage:</label>
        <select id="hideStageSelect">
            <option value="">Select stage</option>
            @foreach(['lead','inquiry','applicant','documents_pending','interview_scheduled','admitted','visa_process','tuition_paid','enrolled','alumni'] as $stage)
                <option value="{{ $stage }}">{{ ucwords(str_replace('_', ' ', $stage)) }}</option>
            @endforeach
        </select>
        <button type="button" class="secondary" id="applyHideStage">Apply</button>
    </div>
    <div class="kanban" id="pipelineBoard">
        @foreach($columns as $name => $items)
            <div class="kanban-col dropzone" data-stage="{{ $name }}">
                <strong>{{ ucwords(str_replace('_', ' ', $name)) }} (<span data-count>{{ $items->count() }}</span>)</strong>
                <div class="kanban-list">
                    @foreach($items as $student)
                        <div class="kanban-item draggable-card" draggable="true" data-student="{{ $student->id }}">
                            <strong>{{ $student->full_name }}</strong>
                            <div class="footer-note">{{ $student->nationality ?: '-' }} | GPA {{ $student->gpa ?: '-' }}</div>
                            <div style="margin-top:6px;display:flex;gap:6px;">
                                <a class="tab" href="/students/{{ $student->id }}">Open</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
<script>
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const hideStageSelect = document.getElementById('hideStageSelect');
    const applyHideStage = document.getElementById('applyHideStage');
    let dragged = null;

    document.querySelectorAll('.draggable-card').forEach((card) => {
        card.addEventListener('dragstart', (event) => {
            dragged = card;
            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', card.dataset.student || '');
            }
            card.classList.add('dragging');
        });
        card.addEventListener('dragend', () => {
            card.classList.remove('dragging');
        });
    });

    document.querySelectorAll('.dropzone').forEach((zone) => {
        zone.addEventListener('dragover', (event) => {
            event.preventDefault();
            zone.classList.add('kanban-hover');
        });
        zone.addEventListener('dragleave', () => zone.classList.remove('kanban-hover'));
        zone.addEventListener('drop', async (event) => {
            event.preventDefault();
            zone.classList.remove('kanban-hover');
            if (!dragged) {
                return;
            }

            const list = zone.querySelector('.kanban-list');
            if (list) {
                list.appendChild(dragged);
            }
            updateCounts();

            const stage = zone.dataset.stage;
            const studentId = dragged.dataset.student;
            const cardOrder = {};
            document.querySelectorAll('.dropzone').forEach((col) => {
                const key = col.dataset.stage;
                cardOrder[key] = Array.from(col.querySelectorAll('.draggable-card')).map((node) => Number(node.dataset.student));
            });

            try {
                const response = await fetch('/pipeline/move', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ student_id: studentId, stage, card_order: cardOrder }),
                });
                if (!response.ok) {
                    throw new Error('Move failed');
                }
            } catch (error) {
                window.location.reload();
            }
        });
    });

    function updateCounts() {
        document.querySelectorAll('.dropzone').forEach((zone) => {
            const count = zone.querySelectorAll('.draggable-card').length;
            const counter = zone.querySelector('[data-count]');
            if (counter) {
                counter.textContent = count;
            }
        });
    }

    if (applyHideStage) {
        applyHideStage.addEventListener('click', async () => {
            const stage = hideStageSelect ? hideStageSelect.value : '';
            if (!stage) return;
            const hidden = [stage];
            await fetch('/pipeline/preferences', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ hidden_stages: hidden }),
            });
            window.location.reload();
        });
    }
</script>
@endsection
