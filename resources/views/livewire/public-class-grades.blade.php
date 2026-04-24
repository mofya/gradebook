<div>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&family=JetBrains+Mono:wght@400;500;600&display=swap');

        .grade-sheet {
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
            font-family: 'DM Sans', system-ui, sans-serif;
        }

        .grade-sheet * { box-sizing: border-box; }

        .gs-title { font-family: 'DM Serif Display', Georgia, serif; }
        .gs-mono { font-family: 'JetBrains Mono', 'Courier New', monospace; }

        .gs-table th,
        .gs-table td {
            padding: 10px 14px;
            white-space: nowrap;
        }

        .gs-table tbody tr {
            transition: background-color 0.15s ease;
        }

        .gs-table tbody tr:nth-child(even) {
            background-color: var(--gs-row-alt);
        }

        .gs-table tbody tr:hover {
            background-color: #ede9e3 !important;
        }

        .gs-sticky-col {
            position: sticky;
            left: 0;
            z-index: 5;
            background: inherit;
        }

        .gs-table tbody tr:nth-child(odd) .gs-sticky-col { background: var(--gs-bg); }
        .gs-table tbody tr:nth-child(even) .gs-sticky-col { background: var(--gs-row-alt); }
        .gs-table tbody tr:hover .gs-sticky-col { background: #ede9e3 !important; }

        .gs-sticky-col::after {
            content: '';
            position: absolute;
            top: 0;
            right: -6px;
            bottom: 0;
            width: 6px;
            background: linear-gradient(to right, rgba(0,0,0,0.04), transparent);
            pointer-events: none;
        }

        .gs-score-cell {
            border-radius: 4px;
            padding: 3px 8px;
            display: inline-block;
            min-width: 48px;
            text-align: center;
            font-weight: 500;
        }

        .gs-search-input {
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.18);
            color: #fff;
            border-radius: 8px;
            padding: 8px 12px 8px 36px;
            font-size: 13px;
            width: 280px;
            transition: all 0.2s ease;
            font-family: 'DM Sans', system-ui, sans-serif;
        }

        .gs-search-input::placeholder { color: rgba(255,255,255,0.45); }
        .gs-search-input:focus {
            outline: none;
            background: rgba(255,255,255,0.18);
            border-color: rgba(255,255,255,0.35);
            box-shadow: 0 0 0 3px rgba(255,255,255,0.08);
        }

        .gs-sort-btn {
            cursor: pointer;
            user-select: none;
            transition: color 0.15s ease;
        }
        .gs-sort-btn:hover { color: var(--gs-text); }

        .gs-fade-in {
            animation: gsFadeIn 0.4s ease-out;
        }

        @keyframes gsFadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .gs-expired-overlay {
            background: linear-gradient(135deg, #f5f0eb 0%, #ebe5dd 100%);
        }
    </style>

    <div class="grade-sheet gs-fade-in" style="min-height: 100vh; background: var(--gs-bg);">

        {{-- Step: Expired --}}
        @if ($step === 'expired')
            <div class="gs-expired-overlay" style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem;">
                <div style="text-align: center; max-width: 400px;">
                    <div style="width: 72px; height: 72px; margin: 0 auto 1.5rem; border-radius: 50%; background: var(--gs-border); display: flex; align-items: center; justify-content: center;">
                        <svg style="width: 32px; height: 32px; color: var(--gs-text-muted);" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <h2 class="gs-title" style="font-size: 1.75rem; color: var(--gs-text); margin-bottom: 0.75rem;">Link Expired</h2>
                    <p style="font-size: 0.875rem; color: var(--gs-text-muted); line-height: 1.7;">
                        This class grades link is no longer active.<br>Please contact your course lecturer for a new link.
                    </p>
                </div>
            </div>

        {{-- Step: Loaded --}}
        @elseif ($step === 'loaded')

            {{-- Header band --}}
            <div style="background: var(--gs-header); color: #fff; padding: 1.5rem 2rem;">
                <div style="max-width: 1400px; margin: 0 auto;">
                    <div style="display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 1rem;">
                        <div>
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
                                <span class="gs-mono" style="font-size: 0.7rem; font-weight: 600; letter-spacing: 0.08em; background: var(--gs-accent); color: #fff; padding: 3px 10px; border-radius: 4px;">
                                    {{ $courseCode }}{{ $section ? " - {$section}" : '' }}
                                </span>
                                <span style="font-size: 0.7rem; color: rgba(255,255,255,0.45); letter-spacing: 0.04em;">{{ $semesterLabel }}</span>
                            </div>
                            <h1 class="gs-title" style="font-size: 1.6rem; font-weight: 400; margin: 0; letter-spacing: -0.01em;">{{ $courseName }}</h1>
                            <p style="font-size: 0.75rem; color: rgba(255,255,255,0.4); margin-top: 4px;">Continuous Assessment Results</p>
                        </div>
                        <div style="position: relative;">
                            <svg style="position: absolute; left: 11px; top: 50%; transform: translateY(-50%); width: 15px; height: 15px; color: rgba(255,255,255,0.4);" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                            <input
                                wire:model.live.debounce.300ms="search"
                                type="text"
                                placeholder="Search student ID or GitHub..."
                                class="gs-search-input"
                            >
                        </div>
                    </div>
                </div>
            </div>

            {{-- Table --}}
            <div style="max-width: 1400px; margin: 0 auto; padding: 1.25rem 1rem 3rem;">
                <div style="overflow-x: auto; border-radius: 10px; border: 1px solid var(--gs-border); background: var(--gs-bg); box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                    <table class="gs-table" style="width: 100%; border-collapse: collapse; font-size: 0.8125rem;">
                        <thead>
                            <tr style="background: var(--gs-header); color: rgba(255,255,255,0.85);">
                                {{-- Student ID --}}
                                <th wire:click="sort('student_id_number')" class="gs-sort-btn gs-sticky-col" style="text-align: left; font-weight: 600; font-size: 0.6875rem; letter-spacing: 0.06em; text-transform: uppercase; background: var(--gs-header); min-width: 130px;">
                                    <span style="display: inline-flex; align-items: center; gap: 4px;">
                                        Student ID
                                        @if ($sortColumn === 'student_id_number')
                                            <svg style="width: 12px; height: 12px; {{ $sortDirection === 'desc' ? 'transform: rotate(180deg);' : '' }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" /></svg>
                                        @endif
                                    </span>
                                </th>

                                {{-- GitHub --}}
                                <th wire:click="sort('github_username')" class="gs-sort-btn" style="text-align: left; font-weight: 600; font-size: 0.6875rem; letter-spacing: 0.06em; text-transform: uppercase;">
                                    <span style="display: inline-flex; align-items: center; gap: 4px;">
                                        <svg style="width: 12px; height: 12px; opacity: 0.6;" viewBox="0 0 16 16" fill="currentColor"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/></svg>
                                        GitHub
                                        @if ($sortColumn === 'github_username')
                                            <svg style="width: 12px; height: 12px; {{ $sortDirection === 'desc' ? 'transform: rotate(180deg);' : '' }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" /></svg>
                                        @endif
                                    </span>
                                </th>

                                {{-- Gender --}}
                                <th style="text-align: center; font-weight: 600; font-size: 0.6875rem; letter-spacing: 0.06em; text-transform: uppercase;">
                                    Gender
                                </th>

                                {{-- Assessments --}}
                                @foreach ($assessmentColumns as $col)
                                    <th style="text-align: center; font-weight: 600; font-size: 0.6875rem; letter-spacing: 0.04em; text-transform: uppercase; min-width: 100px;">
                                        <div style="line-height: 1.3;">{{ $col['name'] }}</div>
                                        <div style="font-weight: 400; font-size: 0.625rem; opacity: 0.45; margin-top: 2px;">out of {{ number_format($col['max_score'], 0) }}</div>
                                    </th>
                                @endforeach

                                {{-- CA totals --}}
                                <th style="text-align: center; font-weight: 600; font-size: 0.6875rem; letter-spacing: 0.06em; text-transform: uppercase; min-width: 90px; border-left: 2px solid var(--gs-border);">
                                    <div style="line-height: 1.3;">CA</div>
                                    <div style="font-weight: 400; font-size: 0.625rem; opacity: 0.45; margin-top: 2px;">out of {{ number_format($caWeight, 0) }}</div>
                                </th>
                                <th style="text-align: center; font-weight: 600; font-size: 0.6875rem; letter-spacing: 0.06em; text-transform: uppercase; min-width: 90px;">
                                    <div style="line-height: 1.3;">CA %</div>
                                    <div style="font-weight: 400; font-size: 0.625rem; opacity: 0.45; margin-top: 2px;">out of 100</div>
                                </th>
                                <th style="text-align: center; font-weight: 600; font-size: 0.6875rem; letter-spacing: 0.06em; text-transform: uppercase; min-width: 80px;">
                                    CA grade
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($students as $index => $student)
                                <tr>
                                    {{-- Student ID --}}
                                    <td class="gs-sticky-col gs-mono" style="font-size: 0.75rem; font-weight: 500; color: var(--gs-text); position: relative;">
                                        {{ $student['student_id_number'] ?: '-' }}
                                    </td>

                                    {{-- GitHub --}}
                                    <td style="color: var(--gs-text-muted); font-size: 0.8125rem;">
                                        @if ($student['github_username'])
                                            <span style="color: var(--gs-accent); font-weight: 500;">{{ $student['github_username'] }}</span>
                                        @else
                                            <span style="color: var(--gs-text-faint);">-</span>
                                        @endif
                                    </td>

                                    {{-- Gender --}}
                                    <td style="text-align: center; font-size: 0.75rem; color: var(--gs-text-muted);">
                                        @if ($student['gender'])
                                            <span style="display: inline-block; padding: 1px 8px; border-radius: 3px; font-size: 0.6875rem; font-weight: 500; letter-spacing: 0.03em; {{ $student['gender'] === 'Male' ? 'background: #eff6ff; color: #3b82f6;' : 'background: #fdf2f8; color: #ec4899;' }}">
                                                {{ $student['gender'] === 'Male' ? 'M' : 'F' }}
                                            </span>
                                        @else
                                            <span style="color: var(--gs-text-faint);">-</span>
                                        @endif
                                    </td>

                                    {{-- Scores --}}
                                    @foreach ($student['scores'] as $si => $score)
                                        <td style="text-align: center;">
                                            @if ($score['is_excused'])
                                                <span style="font-size: 0.6875rem; color: var(--gs-text-faint); font-style: italic; letter-spacing: 0.05em;">EX</span>
                                            @elseif ($score['raw_score'] !== null)
                                                @php
                                                    $pct = $score['max_score'] > 0 ? ($score['raw_score'] / $score['max_score']) * 100 : 0;
                                                    if ($pct >= 70) $bg = 'var(--gs-score-high)';
                                                    elseif ($pct >= 40) $bg = 'var(--gs-score-mid)';
                                                    else $bg = 'var(--gs-score-low)';
                                                @endphp
                                                <span class="gs-score-cell gs-mono" style="background: {{ $bg }}; font-size: 0.75rem; color: var(--gs-text);">
                                                    {{ number_format($score['raw_score'], 1) }}
                                                </span>
                                            @else
                                                <span style="color: var(--gs-text-faint);">-</span>
                                            @endif
                                        </td>
                                    @endforeach

                                    {{-- CA out of weight --}}
                                    <td style="text-align: center; border-left: 2px solid var(--gs-border);">
                                        <span class="gs-mono" style="font-size: 0.8125rem; font-weight: 600; color: var(--gs-text);">
                                            {{ number_format($student['ca_points'] ?? 0, 2) }}
                                        </span>
                                    </td>

                                    {{-- CA % out of 100 --}}
                                    <td style="text-align: center;">
                                        @php
                                            $caPct = $student['ca_out_of_100'];
                                            if ($caPct === null) {
                                                $caBg = 'transparent';
                                            } elseif ($caPct >= 70) {
                                                $caBg = 'var(--gs-score-high)';
                                            } elseif ($caPct >= 40) {
                                                $caBg = 'var(--gs-score-mid)';
                                            } else {
                                                $caBg = 'var(--gs-score-low)';
                                            }
                                        @endphp
                                        @if ($caPct !== null)
                                            <span class="gs-score-cell gs-mono" style="background: {{ $caBg }}; font-size: 0.8125rem; font-weight: 600; color: var(--gs-text);">
                                                {{ number_format($caPct, 1) }}
                                            </span>
                                        @else
                                            <span style="color: var(--gs-text-faint);">-</span>
                                        @endif
                                    </td>

                                    {{-- CA grade --}}
                                    <td style="text-align: center;">
                                        @if (! empty($student['ca_grade']))
                                            <span class="gs-mono" style="display: inline-block; padding: 2px 10px; border-radius: 4px; background: var(--gs-header); color: #fff; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.02em;">
                                                {{ $student['ca_grade'] }}
                                            </span>
                                        @else
                                            <span style="color: var(--gs-text-faint);">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($assessmentColumns) + 6 }}" style="padding: 3rem 1rem; text-align: center; color: var(--gs-text-muted); font-size: 0.875rem;">
                                        @if ($search)
                                            No students found matching <strong>"{{ $search }}"</strong>
                                        @else
                                            No students enrolled yet.
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Footer stats --}}
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 1rem 0.25rem; margin-top: 0.5rem;">
                    <p style="font-size: 0.7rem; color: var(--gs-text-faint); letter-spacing: 0.02em;">
                        @if ($search)
                            Showing {{ count($students) }} of {{ count($allStudents) }} {{ Str::plural('student', count($allStudents)) }}
                        @else
                            {{ count($students) }} {{ Str::plural('student', count($students)) }}
                        @endif
                        &nbsp;&middot;&nbsp;
                        {{ count($assessmentColumns) }} {{ Str::plural('assessment', count($assessmentColumns)) }}
                    </p>
                    <p style="font-size: 0.65rem; color: var(--gs-text-faint); letter-spacing: 0.03em;">
                        {{ config('app.name') }}
                    </p>
                </div>
            </div>

        @endif
    </div>
</div>
