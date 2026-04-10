<style>
    @import url('https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&family=JetBrains+Mono:wght@400;500;600&display=swap');

    :root {
        --gs-bg: #faf8f5;
        --gs-header: #1a1f36;
        --gs-header-accent: #2d3354;
        --gs-border: #e8e4df;
        --gs-text: #2c2c2c;
        --gs-text-muted: #8a8680;
        --gs-text-faint: #bbb6af;
        --gs-row-alt: #f5f2ee;
        --gs-accent: #1a6b4a;
        --gs-accent-light: #e8f5ee;
        --gs-score-high: #dcfce7;
        --gs-score-mid: #fef9c3;
        --gs-score-low: #fee2e2;
    }

    .gs-page {
        min-height: 100vh;
        background: var(--gs-bg);
        font-family: 'DM Sans', sans-serif;
        color: var(--gs-text);
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 0;
    }

    .gs-fade-in {
        animation: gsFadeIn 0.4s ease-out both;
    }

    @keyframes gsFadeIn {
        from { opacity: 0; transform: translateY(12px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .gs-header-band {
        width: 100%;
        background: var(--gs-header);
        padding: 2.5rem 1.5rem 2rem;
        text-align: center;
    }

    .gs-header-band .gs-course-badge {
        display: inline-block;
        background: var(--gs-header-accent);
        color: rgba(255,255,255,0.7);
        font-family: 'JetBrains Mono', monospace;
        font-size: 0.75rem;
        font-weight: 500;
        letter-spacing: 0.08em;
        padding: 0.35rem 1rem;
        border-radius: 20px;
        text-transform: uppercase;
    }

    .gs-header-band .gs-course-title {
        font-family: 'DM Serif Display', serif;
        color: #ffffff;
        font-size: 1.5rem;
        margin: 0.75rem 0 0.25rem;
        font-weight: 400;
    }

    .gs-header-band .gs-semester-label {
        color: rgba(255,255,255,0.45);
        font-size: 0.8rem;
        font-weight: 400;
    }

    .gs-content-area {
        width: 100%;
        max-width: 36rem;
        padding: 2rem 1rem 3rem;
        flex: 1;
    }

    .gs-content-area.gs-wide {
        max-width: 44rem;
    }

    .gs-card {
        background: #ffffff;
        border: 1px solid var(--gs-border);
        border-radius: 10px;
        overflow: hidden;
    }

    .gs-card-body {
        padding: 2rem;
    }

    /* Expired step */
    .gs-expired-icon {
        width: 3.5rem;
        height: 3.5rem;
        border-radius: 50%;
        background: var(--gs-row-alt);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
    }

    .gs-expired-icon svg {
        width: 1.75rem;
        height: 1.75rem;
        color: var(--gs-text-muted);
    }

    .gs-title {
        font-family: 'DM Serif Display', serif;
        font-size: 1.25rem;
        color: var(--gs-text);
        margin: 0;
        font-weight: 400;
    }

    .gs-subtitle {
        color: var(--gs-text-muted);
        font-size: 0.875rem;
        line-height: 1.6;
        margin-top: 0.5rem;
    }

    /* Form elements */
    .gs-label {
        display: block;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--gs-text);
        margin-bottom: 0.4rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .gs-input {
        display: block;
        width: 100%;
        padding: 0.7rem 0.9rem;
        font-family: 'JetBrains Mono', monospace;
        font-size: 0.9rem;
        color: var(--gs-text);
        background: #ffffff;
        border: 1px solid var(--gs-border);
        border-radius: 8px;
        outline: none;
        transition: border-color 0.2s, box-shadow 0.2s;
        box-sizing: border-box;
    }

    .gs-input::placeholder {
        color: var(--gs-text-faint);
        font-family: 'DM Sans', sans-serif;
    }

    .gs-input:focus {
        border-color: var(--gs-accent);
        box-shadow: 0 0 0 3px rgba(26,107,74,0.1);
    }

    .gs-error-text {
        color: #dc2626;
        font-size: 0.8rem;
        margin-top: 0.4rem;
    }

    .gs-btn-primary {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        width: 100%;
        padding: 0.7rem 1rem;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.875rem;
        font-weight: 600;
        color: #ffffff;
        background: var(--gs-accent);
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: background 0.2s, transform 0.1s;
        margin-top: 1.25rem;
    }

    .gs-btn-primary:hover {
        background: #155e3f;
    }

    .gs-btn-primary:active {
        transform: scale(0.98);
    }

    .gs-btn-primary:disabled {
        opacity: 0.55;
        cursor: not-allowed;
    }

    .gs-btn-secondary {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        padding: 0.65rem 1rem;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--gs-text);
        background: #ffffff;
        border: 1px solid var(--gs-border);
        border-radius: 8px;
        cursor: pointer;
        transition: background 0.15s;
        margin-top: 1rem;
    }

    .gs-btn-secondary:hover {
        background: var(--gs-row-alt);
    }

    /* Error alert */
    .gs-alert-error {
        background: #fef2f2;
        border: 1px solid #fecaca;
        border-radius: 8px;
        padding: 1rem;
        display: flex;
        gap: 0.75rem;
    }

    .gs-alert-error svg {
        width: 1.25rem;
        height: 1.25rem;
        color: #ef4444;
        flex-shrink: 0;
        margin-top: 0.1rem;
    }

    .gs-alert-error .gs-alert-title {
        font-size: 0.85rem;
        font-weight: 600;
        color: #991b1b;
        margin: 0;
    }

    .gs-alert-error .gs-alert-message {
        font-size: 0.8rem;
        color: #b91c1c;
        line-height: 1.5;
        margin-top: 0.25rem;
    }

    /* Student info header in found state */
    .gs-student-header {
        padding: 1.5rem 2rem;
        background: var(--gs-header);
        border-radius: 10px 10px 0 0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 0.75rem;
    }

    .gs-student-name {
        font-family: 'DM Serif Display', serif;
        font-size: 1.2rem;
        color: #ffffff;
        margin: 0;
        font-weight: 400;
    }

    .gs-student-id {
        font-family: 'JetBrains Mono', monospace;
        font-size: 0.8rem;
        color: rgba(255,255,255,0.5);
        margin-top: 0.15rem;
    }

    .gs-github-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        background: var(--gs-header-accent);
        color: rgba(255,255,255,0.7);
        font-family: 'JetBrains Mono', monospace;
        font-size: 0.75rem;
        font-weight: 500;
        padding: 0.3rem 0.75rem;
        border-radius: 20px;
    }

    .gs-github-badge svg {
        width: 0.875rem;
        height: 0.875rem;
    }

    /* Grade groups */
    .gs-grades-body {
        padding: 1.5rem 2rem 2rem;
    }

    .gs-group {
        margin-bottom: 1.5rem;
    }

    .gs-group:last-child {
        margin-bottom: 0;
    }

    .gs-group-title {
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--gs-text-muted);
        margin: 0 0 0.6rem;
        padding-bottom: 0.4rem;
        border-bottom: 1px solid var(--gs-border);
    }

    .gs-assessment-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
    }

    .gs-assessment-table thead th {
        padding: 0.5rem 0.75rem;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--gs-text-faint);
        text-align: left;
        border-bottom: 1px solid var(--gs-border);
    }

    .gs-assessment-table thead th:last-child {
        text-align: right;
    }

    .gs-assessment-table tbody tr {
        border-bottom: 1px solid var(--gs-border);
    }

    .gs-assessment-table tbody tr:last-child {
        border-bottom: none;
    }

    .gs-assessment-table tbody tr:nth-child(even) {
        background: var(--gs-row-alt);
    }

    .gs-assessment-table tbody td {
        padding: 0.6rem 0.75rem;
    }

    .gs-assessment-table tbody td:first-child {
        color: var(--gs-text);
        font-weight: 500;
    }

    .gs-assessment-table tbody td:last-child {
        text-align: right;
    }

    .gs-score-pill {
        display: inline-block;
        font-family: 'JetBrains Mono', monospace;
        font-size: 0.8rem;
        font-weight: 500;
        padding: 0.15rem 0.6rem;
        border-radius: 4px;
    }

    .gs-score-high {
        background: var(--gs-score-high);
        color: #166534;
    }

    .gs-score-mid {
        background: var(--gs-score-mid);
        color: #854d0e;
    }

    .gs-score-low {
        background: var(--gs-score-low);
        color: #991b1b;
    }

    .gs-score-max {
        font-family: 'JetBrains Mono', monospace;
        font-size: 0.75rem;
        color: var(--gs-text-faint);
        margin-left: 0.25rem;
    }

    .gs-excused {
        font-style: italic;
        color: var(--gs-text-faint);
        font-size: 0.8rem;
    }

    .gs-no-score {
        color: var(--gs-text-faint);
        font-family: 'JetBrains Mono', monospace;
        font-size: 0.8rem;
    }

    .gs-empty-state {
        text-align: center;
        padding: 2rem 0;
        color: var(--gs-text-muted);
        font-size: 0.875rem;
    }

    .gs-found-footer {
        padding: 0 2rem 2rem;
    }

    /* Footer */
    .gs-footer {
        text-align: center;
        padding: 1.5rem 0 2rem;
        font-size: 0.7rem;
        color: var(--gs-text-faint);
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    /* Spinner */
    .gs-spinner {
        display: inline-block;
        width: 1rem;
        height: 1rem;
        border: 2px solid rgba(255,255,255,0.3);
        border-top-color: #ffffff;
        border-radius: 50%;
        animation: gsSpin 0.6s linear infinite;
    }

    @keyframes gsSpin {
        to { transform: rotate(360deg); }
    }

    /* Responsive */
    @media (max-width: 480px) {
        .gs-header-band {
            padding: 2rem 1rem 1.5rem;
        }
        .gs-header-band .gs-course-title {
            font-size: 1.25rem;
        }
        .gs-card-body {
            padding: 1.5rem;
        }
        .gs-student-header {
            padding: 1.25rem 1.5rem;
        }
        .gs-grades-body {
            padding: 1.25rem 1.5rem 1.5rem;
        }
        .gs-found-footer {
            padding: 0 1.5rem 1.5rem;
        }
    }
</style>

<div class="gs-page">

    {{-- Course header band --}}
    @if ($step !== 'expired')
        <div class="gs-header-band gs-fade-in">
            <span class="gs-course-badge">{{ $courseCode }}</span>
            <h1 class="gs-course-title">{{ $courseName }}</h1>
            <p class="gs-semester-label">{{ $semesterLabel }}</p>
        </div>
    @endif

    {{-- Main content area --}}
    <div class="gs-content-area {{ $step === 'found' ? 'gs-wide' : '' }} gs-fade-in" style="animation-delay: 0.1s;">

        {{-- Step: Expired --}}
        @if ($step === 'expired')
            <div style="padding-top: 4rem;">
                <div class="gs-card">
                    <div class="gs-card-body" style="text-align: center;">
                        <div class="gs-expired-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </div>
                        <h2 class="gs-title">Link Expired</h2>
                        <p class="gs-subtitle">
                            This grades link is no longer active. Please contact your course lecturer for a new link.
                        </p>
                    </div>
                </div>
            </div>

        {{-- Step: Lookup --}}
        @elseif ($step === 'lookup')
            <div class="gs-card">
                <div class="gs-card-body">
                    <h2 class="gs-title">View Your Grades</h2>
                    <p class="gs-subtitle">Enter your student ID to see your CA results.</p>

                    <form wire:submit="viewGrades" style="margin-top: 1.5rem;">
                        <label for="studentIdNumber" class="gs-label">Student ID Number</label>
                        <input
                            wire:model="studentIdNumber"
                            type="text"
                            id="studentIdNumber"
                            placeholder="e.g. 2023123456"
                            autocomplete="off"
                            class="gs-input"
                        >
                        @error('studentIdNumber')
                            <p class="gs-error-text">{{ $message }}</p>
                        @enderror

                        <button
                            type="submit"
                            class="gs-btn-primary"
                            wire:loading.attr="disabled"
                        >
                            <span wire:loading.remove wire:target="viewGrades">View Grades</span>
                            <span wire:loading wire:target="viewGrades" style="display: flex; align-items: center; gap: 0.5rem;">
                                <span class="gs-spinner"></span>
                                Loading...
                            </span>
                        </button>
                    </form>
                </div>
            </div>

        {{-- Step: Not Found --}}
        @elseif ($step === 'not_found')
            <div class="gs-card">
                <div class="gs-card-body">
                    <div class="gs-alert-error">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                        </svg>
                        <div>
                            <p class="gs-alert-title">Not Found</p>
                            <p class="gs-alert-message">{{ $errorMessage }}</p>
                        </div>
                    </div>
                    <button
                        wire:click="resetLookup"
                        class="gs-btn-secondary"
                    >
                        Try Again
                    </button>
                </div>
            </div>

        {{-- Step: Found --}}
        @elseif ($step === 'found')
            <div class="gs-card">
                {{-- Student info header --}}
                <div class="gs-student-header">
                    <div>
                        <h2 class="gs-student-name">{{ $studentName }}</h2>
                        <p class="gs-student-id">{{ $studentIdNumber }}</p>
                    </div>
                    @if ($githubUsername)
                        <span class="gs-github-badge">
                            <svg viewBox="0 0 16 16" fill="currentColor"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/></svg>
                            {{ $githubUsername }}
                        </span>
                    @endif
                </div>

                {{-- Grades --}}
                <div class="gs-grades-body">
                    @forelse ($gradeData as $group)
                        <div class="gs-group">
                            <h3 class="gs-group-title">{{ $group['group_name'] }}</h3>
                            <table class="gs-assessment-table">
                                <thead>
                                    <tr>
                                        <th>Assessment</th>
                                        <th>Score</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($group['assessments'] as $assessment)
                                        <tr>
                                            <td>{{ $assessment['name'] }}</td>
                                            <td>
                                                @if ($assessment['is_excused'])
                                                    <span class="gs-excused">Excused</span>
                                                @elseif ($assessment['raw_score'] !== null)
                                                    @php
                                                        $pct = $assessment['max_score'] > 0 ? ($assessment['raw_score'] / $assessment['max_score']) * 100 : 0;
                                                        $scoreClass = $pct >= 70 ? 'gs-score-high' : ($pct >= 40 ? 'gs-score-mid' : 'gs-score-low');
                                                    @endphp
                                                    <span class="gs-score-pill {{ $scoreClass }}">{{ number_format($assessment['raw_score'], 1) }}</span>
                                                    <span class="gs-score-max">/ {{ number_format($assessment['max_score'], 0) }}</span>
                                                @else
                                                    <span class="gs-no-score">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @empty
                        <p class="gs-empty-state">No assessments have been set up yet.</p>
                    @endforelse
                </div>

                {{-- Back button --}}
                <div class="gs-found-footer">
                    <button
                        wire:click="resetLookup"
                        class="gs-btn-secondary"
                    >
                        Look Up Another Student
                    </button>
                </div>
            </div>
        @endif

        {{-- Footer --}}
        <div class="gs-footer">
            {{ config('app.name') }}
        </div>
    </div>
</div>
