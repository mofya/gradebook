<div>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&family=JetBrains+Mono:wght@400;500;600&display=swap');

        :root {
            --gs-bg: #faf8f5;
            --gs-header: #1a1f36;
            --gs-border: #e8e4df;
            --gs-text: #2c2c2c;
            --gs-text-muted: #8a8680;
            --gs-accent: #1a6b4a;
            --gs-accent-light: #e8f5ee;
            --gs-danger: #b4322a;
            --gs-danger-light: #fceceb;
        }

        .gs-page {
            min-height: 100vh;
            background: var(--gs-bg);
            font-family: 'DM Sans', sans-serif;
            color: var(--gs-text);
            padding: 48px 16px;
        }

        .gs-wrapper {
            max-width: 620px;
            margin: 0 auto;
        }

        .gs-card {
            background: #fff;
            border: 1px solid var(--gs-border);
            border-radius: 10px;
            overflow: hidden;
        }

        .gs-header {
            background: var(--gs-header);
            padding: 20px 28px;
            color: #fff;
        }

        .gs-course-code {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            opacity: 0.7;
        }

        .gs-title {
            font-family: 'DM Serif Display', serif;
            font-size: 24px;
            margin: 4px 0 0;
        }

        .gs-subtitle {
            font-size: 13px;
            opacity: 0.75;
            margin-top: 4px;
        }

        .gs-body {
            padding: 28px;
        }

        .gs-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .gs-hint {
            font-size: 12px;
            color: var(--gs-text-muted);
            margin: -4px 0 8px;
        }

        .gs-input,
        .gs-textarea,
        .gs-file {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--gs-border);
            border-radius: 6px;
            font-family: inherit;
            font-size: 14px;
            background: #fff;
        }

        .gs-textarea {
            min-height: 90px;
            resize: vertical;
        }

        .gs-btn {
            display: inline-block;
            padding: 10px 18px;
            border: none;
            border-radius: 6px;
            background: var(--gs-accent);
            color: #fff;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
        }

        .gs-btn:hover {
            opacity: 0.9;
        }

        .gs-btn-secondary {
            background: transparent;
            color: var(--gs-text);
            border: 1px solid var(--gs-border);
        }

        .gs-error {
            background: var(--gs-danger-light);
            color: var(--gs-danger);
            padding: 10px 14px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 16px;
        }

        .gs-success {
            background: var(--gs-accent-light);
            color: var(--gs-accent);
            padding: 14px 18px;
            border-radius: 6px;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .gs-field {
            margin-bottom: 18px;
        }

        .gs-section-title {
            font-weight: 600;
            font-size: 14px;
            margin: 0 0 8px;
        }

        .gs-assessment-group {
            margin-bottom: 14px;
        }

        .gs-group-label {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--gs-text-muted);
            margin-bottom: 6px;
        }

        .gs-check {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            padding: 8px 10px;
            border-radius: 6px;
            cursor: pointer;
        }

        .gs-check:hover {
            background: var(--gs-accent-light);
        }

        .gs-check input[type="checkbox"] {
            margin-top: 2px;
        }

        .gs-check-label {
            font-size: 14px;
            flex: 1;
        }

        .gs-dean-box {
            background: #fff9ea;
            border: 1px solid #eadbaf;
            border-radius: 6px;
            padding: 14px 16px;
            margin-bottom: 18px;
        }

        .gs-footer {
            text-align: center;
            font-size: 11px;
            color: var(--gs-text-muted);
            margin-top: 24px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        .gs-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
    </style>

    <div class="gs-page">
        <div class="gs-wrapper">

            {{-- Header --}}
            <div class="gs-card" style="margin-bottom: 20px;">
                <div class="gs-header">
                    <div class="gs-course-code">{{ $courseCode }} · {{ $semesterLabel }}</div>
                    <h1 class="gs-title">Missed Assessment Appeal</h1>
                    <div class="gs-subtitle">{{ $courseName }}</div>
                </div>
            </div>

            @if ($step === 'expired')
                <div class="gs-card">
                    <div class="gs-body">
                        <div class="gs-error">This appeal link has expired or is invalid. Please contact your lecturer.</div>
                    </div>
                </div>

            @elseif ($step === 'lookup')
                <div class="gs-card">
                    <div class="gs-body">
                        <p class="gs-subtitle" style="color: var(--gs-text-muted); margin-bottom: 16px;">
                            Enter your student ID number to start your appeal.
                        </p>

                        @if ($errorMessage)
                            <div class="gs-error">{{ $errorMessage }}</div>
                        @endif

                        <div class="gs-field">
                            <label for="sid" class="gs-label">Student ID Number</label>
                            <input
                                id="sid"
                                type="text"
                                wire:model="studentIdNumber"
                                wire:keydown.enter="lookupStudent"
                                placeholder="e.g. 2023000645"
                                class="gs-input"
                                autocomplete="off"
                            />
                            @error('studentIdNumber')
                                <div class="gs-error" style="margin-top: 8px;">{{ $message }}</div>
                            @enderror
                        </div>

                        <button wire:click="lookupStudent" class="gs-btn" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="lookupStudent">Continue</span>
                            <span wire:loading wire:target="lookupStudent">Looking up…</span>
                        </button>
                    </div>
                </div>

            @elseif ($step === 'form')
                <div class="gs-card">
                    <div class="gs-body">
                        <p style="margin: 0 0 18px;">
                            <strong>{{ $studentName }}</strong><br>
                            <span class="gs-subtitle" style="color: var(--gs-text-muted);">{{ $studentEmail }}</span>
                        </p>

                        @if ($errorMessage)
                            <div class="gs-error">{{ $errorMessage }}</div>
                        @endif

                        @if (count($availableAssessments) === 0)
                            <div class="gs-error">
                                You have grades for all assessments in this course, so there is nothing to appeal.
                                If you believe this is wrong, contact your lecturer.
                            </div>
                        @else
                            {{-- Assessment picker --}}
                            <div class="gs-field">
                                <p class="gs-section-title">Assessments you missed</p>
                                <p class="gs-hint">Select every assessment in this course that you did not attempt.</p>

                                @php
                                    $grouped = collect($availableAssessments)->groupBy('group');
                                @endphp

                                @foreach ($grouped as $groupName => $items)
                                    <div class="gs-assessment-group">
                                        <div class="gs-group-label">{{ $groupName ?: 'Assessments' }}</div>
                                        @foreach ($items as $item)
                                            <label class="gs-check">
                                                <input
                                                    type="checkbox"
                                                    wire:model="selectedAssessmentIds"
                                                    value="{{ $item['id'] }}"
                                                />
                                                <span class="gs-check-label">{{ $item['name'] }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                @endforeach

                                @error('selectedAssessmentIds')
                                    <div class="gs-error" style="margin-top: 8px;">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Narrative --}}
                            <div class="gs-field">
                                <label class="gs-label" for="narrative">Why did you miss these assessments?</label>
                                <p class="gs-hint">Explain clearly and briefly. Your lecturer will read this.</p>
                                <textarea
                                    id="narrative"
                                    wire:model="narrative"
                                    class="gs-textarea"
                                    rows="4"
                                    placeholder="e.g. I was admitted to hospital from … to …"
                                ></textarea>
                                @error('narrative')
                                    <div class="gs-error" style="margin-top: 8px;">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Other notes --}}
                            <div class="gs-field">
                                <label class="gs-label" for="other">Other notes <span style="color: var(--gs-text-muted); font-weight: 400;">(optional)</span></label>
                                <p class="gs-hint">Anything else not tied to a specific assessment.</p>
                                <textarea
                                    id="other"
                                    wire:model="otherNotes"
                                    class="gs-textarea"
                                    rows="2"
                                ></textarea>
                            </div>

                            {{-- Assistant Dean confirmation --}}
                            <div class="gs-dean-box">
                                <label class="gs-check" style="padding: 0;">
                                    <input type="checkbox" wire:model="deanConfirmed" />
                                    <span class="gs-check-label">
                                        <strong>I have contacted the Assistant Dean (Undergraduate)</strong><br>
                                        <span style="font-size: 13px; color: var(--gs-text-muted);">
                                            All missed-assessment appeals must first go through the Assistant Dean.
                                            Please attach the letter or email below.
                                        </span>
                                    </span>
                                </label>
                                @error('deanConfirmed')
                                    <div class="gs-error" style="margin-top: 10px;">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Evidence --}}
                            <div class="gs-field">
                                <label class="gs-label" for="evidence">Evidence of contact with the Assistant Dean <span style="color: var(--gs-text-muted); font-weight: 400;">(optional)</span></label>
                                <p class="gs-hint">PDF, PNG, JPG, DOC, or DOCX — max 10 MB.</p>
                                <input type="file" id="evidence" wire:model="evidenceFile" class="gs-file" accept=".pdf,.png,.jpg,.jpeg,.doc,.docx" />
                                <div wire:loading wire:target="evidenceFile" style="font-size: 12px; color: var(--gs-text-muted); margin-top: 6px;">Uploading…</div>
                                @error('evidenceFile')
                                    <div class="gs-error" style="margin-top: 8px;">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="gs-actions">
                                <button wire:click="submit" class="gs-btn" wire:loading.attr="disabled">
                                    <span wire:loading.remove wire:target="submit">Submit appeal</span>
                                    <span wire:loading wire:target="submit">Submitting…</span>
                                </button>
                            </div>
                        @endif
                    </div>
                </div>

            @elseif ($step === 'submitted')
                <div class="gs-card">
                    <div class="gs-body">
                        <div class="gs-success">
                            <strong>Appeal submitted.</strong><br>
                            Your lecturer will review it and be in touch.
                        </div>
                        <p style="font-size: 14px; color: var(--gs-text-muted);">
                            Keep a record of your Assistant Dean communication — you may be asked to re-confirm it.
                        </p>
                    </div>
                </div>
            @endif

            <div class="gs-footer">{{ config('app.name') }}</div>
        </div>
    </div>
</div>
