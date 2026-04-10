<div>
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
        }

        .gs-page {
            min-height: 100vh;
            background: var(--gs-bg);
            font-family: 'DM Sans', sans-serif;
            color: var(--gs-text);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 48px 16px;
        }

        .gs-title {
            font-family: 'DM Serif Display', serif;
            font-weight: 400;
        }

        .gs-mono {
            font-family: 'JetBrains Mono', monospace;
        }

        .gs-card {
            width: 100%;
            max-width: 440px;
            background: #ffffff;
            border: 1px solid var(--gs-border);
            border-radius: 10px;
            overflow: hidden;
        }

        .gs-fade-in {
            animation: gsFadeIn 0.4s ease-out both;
        }

        @keyframes gsFadeIn {
            from {
                opacity: 0;
                transform: translateY(8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .gs-header-band {
            background: var(--gs-header);
            padding: 20px 28px;
            text-align: center;
        }

        .gs-header-band .gs-course-code {
            display: inline-block;
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.55);
            background: var(--gs-header-accent);
            padding: 4px 12px;
            border-radius: 4px;
            margin-bottom: 8px;
        }

        .gs-header-band .gs-course-name {
            font-family: 'DM Serif Display', serif;
            font-size: 20px;
            color: #ffffff;
            margin: 0;
            line-height: 1.3;
        }

        .gs-header-band .gs-semester {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.45);
            margin-top: 4px;
        }

        .gs-body {
            padding: 32px 28px;
        }

        .gs-input {
            display: block;
            width: 100%;
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            color: var(--gs-text);
            background: #ffffff;
            border: 1px solid var(--gs-border);
            border-radius: 8px;
            padding: 10px 14px;
            outline: none;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
            box-sizing: border-box;
        }

        .gs-input:focus {
            border-color: var(--gs-accent);
            box-shadow: 0 0 0 3px rgba(26, 107, 74, 0.12);
        }

        .gs-input::placeholder {
            color: var(--gs-text-faint);
        }

        .gs-input-prefix {
            display: flex;
            align-items: center;
        }

        .gs-input-prefix .gs-prefix-label {
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            color: var(--gs-text-muted);
            background: var(--gs-row-alt);
            border: 1px solid var(--gs-border);
            border-right: none;
            border-radius: 8px 0 0 8px;
            padding: 10px 12px;
            white-space: nowrap;
        }

        .gs-input-prefix .gs-input {
            border-radius: 0 8px 8px 0;
        }

        .gs-select {
            display: block;
            width: 100%;
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            color: var(--gs-text);
            background: #ffffff;
            border: 1px solid var(--gs-border);
            border-radius: 8px;
            padding: 10px 14px;
            outline: none;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
            box-sizing: border-box;
            appearance: auto;
        }

        .gs-select:focus {
            border-color: var(--gs-accent);
            box-shadow: 0 0 0 3px rgba(26, 107, 74, 0.12);
        }

        .gs-btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            font-weight: 600;
            color: #ffffff;
            background: var(--gs-accent);
            border: none;
            border-radius: 8px;
            padding: 11px 20px;
            cursor: pointer;
            transition: background 0.15s ease, opacity 0.15s ease;
        }

        .gs-btn-primary:hover {
            background: #155a3e;
        }

        .gs-btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .gs-btn-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            font-weight: 500;
            color: var(--gs-text);
            background: #ffffff;
            border: 1px solid var(--gs-border);
            border-radius: 8px;
            padding: 11px 20px;
            cursor: pointer;
            transition: background 0.15s ease;
        }

        .gs-btn-secondary:hover {
            background: var(--gs-row-alt);
        }

        .gs-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--gs-text);
            margin-bottom: 6px;
        }

        .gs-hint {
            font-size: 12px;
            color: var(--gs-text-muted);
            margin-bottom: 6px;
        }

        .gs-error-text {
            font-size: 13px;
            color: #c53030;
            margin-top: 6px;
        }

        .gs-divider {
            border: none;
            border-top: 1px solid var(--gs-border);
            margin: 0;
        }

        .gs-info-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 0;
        }

        .gs-info-row + .gs-info-row {
            border-top: 1px solid var(--gs-border);
        }

        .gs-info-label {
            font-size: 13px;
            color: var(--gs-text-muted);
        }

        .gs-info-value {
            font-size: 13px;
            font-weight: 500;
            color: var(--gs-text);
        }

        .gs-icon-circle {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px auto;
        }

        .gs-alert-warning {
            background: #fefce8;
            border: 1px solid #f3d98e;
            border-radius: 8px;
            padding: 12px 14px;
            margin-top: 8px;
        }

        .gs-alert-success {
            background: var(--gs-accent-light);
            border: 1px solid #b2dfc6;
            border-radius: 8px;
            padding: 12px 14px;
            margin-top: 8px;
        }

        .gs-alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 14px;
        }

        .gs-btn-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }

        .gs-btn-actions > * {
            flex: 1;
        }

        .gs-section-label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--gs-text-faint);
            margin-bottom: 16px;
        }

        .gs-field-group {
            margin-bottom: 16px;
        }

        .gs-field-group:last-child {
            margin-bottom: 0;
        }

        .gs-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 12px;
            color: var(--gs-text-faint);
        }

        .gs-spinner {
            width: 16px;
            height: 16px;
            animation: gsSpin 0.7s linear infinite;
        }

        @keyframes gsSpin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .gs-btn-dispute {
            display: inline-flex;
            align-items: center;
            font-family: 'DM Sans', sans-serif;
            font-size: 12px;
            font-weight: 600;
            color: #ffffff;
            background: #b45309;
            border: none;
            border-radius: 6px;
            padding: 6px 12px;
            margin-top: 8px;
            cursor: pointer;
            transition: background 0.15s ease;
        }

        .gs-btn-dispute:hover {
            background: #92400e;
        }

        .gs-backfill-notice {
            background: var(--gs-accent-light);
            border: 1px solid #b2dfc6;
            border-radius: 8px;
            padding: 12px 14px;
            margin-top: 16px;
            font-size: 13px;
            color: var(--gs-accent);
            text-align: center;
        }
    </style>

    <div class="gs-page">

        {{-- Course header band --}}
        @if ($step !== 'expired')
            <div class="gs-card gs-fade-in" style="margin-bottom: 20px; overflow: hidden;">
                <div class="gs-header-band">
                    <div class="gs-course-code">{{ $courseCode }}</div>
                    <h1 class="gs-course-name">{{ $courseName }}</h1>
                    <p class="gs-semester" style="margin: 4px 0 0 0;">{{ $semesterLabel }}</p>
                </div>
            </div>
        @endif

        {{-- Main card --}}
        <div class="gs-card gs-fade-in">

            {{-- Step: Expired --}}
            @if ($step === 'expired')
                <div class="gs-body" style="text-align: center;">
                    <div class="gs-icon-circle" style="background: var(--gs-row-alt);">
                        <svg style="width: 28px; height: 28px; color: var(--gs-text-muted);" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <h2 class="gs-title" style="font-size: 22px; color: var(--gs-text); margin: 0;">Link Expired</h2>
                    <p style="font-size: 14px; color: var(--gs-text-muted); line-height: 1.6; margin: 10px 0 0 0;">
                        This verification link is no longer active. Please contact your course lecturer for a new link.
                    </p>
                </div>

            {{-- Step: Lookup --}}
            @elseif ($step === 'lookup')
                <div class="gs-body">
                    <h2 class="gs-title" style="font-size: 22px; color: var(--gs-text); margin: 0;">Verify Your Details</h2>
                    <p style="font-size: 14px; color: var(--gs-text-muted); margin: 6px 0 0 0;">Enter your student ID to get started.</p>

                    <form wire:submit="verifyStudent" style="margin-top: 24px;">
                        <label for="studentIdNumber" class="gs-label">Student ID Number</label>
                        <input
                            wire:model="studentIdNumber"
                            type="text"
                            id="studentIdNumber"
                            placeholder="e.g. 2023123456"
                            autocomplete="off"
                            class="gs-input gs-mono"
                        >
                        @error('studentIdNumber')
                            <p class="gs-error-text">{{ $message }}</p>
                        @enderror

                        <button
                            type="submit"
                            class="gs-btn-primary"
                            style="margin-top: 20px;"
                            wire:loading.attr="disabled"
                        >
                            <span wire:loading.remove wire:target="verifyStudent">Verify</span>
                            <span wire:loading wire:target="verifyStudent" style="display: none; align-items: center; gap: 8px;">
                                <svg class="gs-spinner" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                </svg>
                                Checking...
                            </span>
                        </button>
                    </form>
                </div>

            {{-- Step: Not Found --}}
            @elseif ($step === 'not_found')
                <div class="gs-body">
                    <div class="gs-alert-error">
                        <div style="display: flex; gap: 12px;">
                            <svg style="width: 20px; height: 20px; flex-shrink: 0; margin-top: 1px; color: #dc2626;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                            </svg>
                            <div>
                                <p style="font-size: 14px; font-weight: 600; color: #991b1b; margin: 0;">Not Found</p>
                                <p style="font-size: 13px; color: #b91c1c; line-height: 1.5; margin: 4px 0 0 0;">{{ $errorMessage }}</p>
                            </div>
                        </div>
                    </div>
                    <button
                        wire:click="resetLookup"
                        class="gs-btn-secondary"
                        style="margin-top: 20px;"
                    >
                        Try Again
                    </button>
                </div>

            {{-- Step: Review --}}
            @elseif ($step === 'review')
                <div class="gs-body">
                    <h2 class="gs-title" style="font-size: 22px; color: var(--gs-text); margin: 0;">Hello, {{ $studentName }}</h2>
                    <p style="font-size: 14px; color: var(--gs-text-muted); margin: 6px 0 0 0;">Here is what we have on file for you. Please check that your details are correct.</p>

                    <div style="margin-top: 24px; background: var(--gs-row-alt); border-radius: 8px; padding: 4px 16px;">
                        <div class="gs-info-row">
                            <span class="gs-info-label">Student ID</span>
                            <span class="gs-info-value gs-mono">{{ $studentIdNumber }}</span>
                        </div>
                        <div class="gs-info-row">
                            <span class="gs-info-label">Email</span>
                            <span class="gs-info-value">{{ $studentEmail }}</span>
                        </div>
                        <div class="gs-info-row">
                            <span class="gs-info-label">GitHub Username</span>
                            @if ($currentGithub)
                                <span class="gs-info-value gs-mono" style="color: var(--gs-accent);">{{ $currentGithub }}</span>
                            @else
                                <span style="font-size: 13px; color: var(--gs-text-faint); font-style: italic;">Not set</span>
                            @endif
                        </div>
                        <div class="gs-info-row">
                            <span class="gs-info-label">Gender</span>
                            @if ($gender)
                                <span class="gs-info-value">{{ $gender }}</span>
                            @else
                                <span style="font-size: 13px; color: var(--gs-text-faint); font-style: italic;">Not set</span>
                            @endif
                        </div>
                    </div>

                    @if ($errorMessage)
                        <div class="gs-alert-warning" style="margin-top: 16px;">
                            <div style="display: flex; gap: 10px; align-items: start;">
                                <svg style="width: 18px; height: 18px; flex-shrink: 0; margin-top: 1px; color: #92400e;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                </svg>
                                <p style="font-size: 13px; color: #92400e; margin: 0; line-height: 1.5;">{{ $errorMessage }}</p>
                            </div>
                        </div>
                    @endif

                    <div class="gs-btn-actions">
                        <button
                            type="button"
                            wire:click="confirmDetails"
                            class="gs-btn-secondary"
                        >
                            This looks correct
                        </button>
                        <button
                            type="button"
                            wire:click="proceedToEdit"
                            class="gs-btn-primary"
                        >
                            Update my details
                        </button>
                    </div>
                </div>

            {{-- Step: Found (Edit form) --}}
            @elseif ($step === 'found')
                {{-- Editable fields --}}
                <form wire:submit="updateDetails" class="gs-body">
                    <h2 class="gs-title" style="font-size: 22px; color: var(--gs-text); margin: 0 0 6px 0;">Update Your Details</h2>
                    <p style="font-size: 14px; color: var(--gs-text-muted); margin: 0 0 24px 0;">Make changes below and save when you're done.</p>

                    <div class="gs-field-group">
                        <label for="githubUsername" class="gs-label">GitHub Username</label>
                        <div class="gs-input-prefix">
                            <span class="gs-prefix-label">github.com/</span>
                            <input
                                wire:model="githubUsername"
                                type="text"
                                id="githubUsername"
                                placeholder="your-username"
                                class="gs-input gs-mono"
                            >
                        </div>
                        @error('githubUsername')
                            <p class="gs-error-text">{{ $message }}</p>
                        @enderror
                        @if ($showDisputeOption)
                            <div class="gs-alert-warning">
                                <p style="font-size: 13px; color: #92400e; margin: 0; line-height: 1.5;">If this is your GitHub account, you can file a dispute and your lecturer will review it.</p>
                                <button
                                    type="button"
                                    wire:click="fileDispute"
                                    class="gs-btn-dispute"
                                >
                                    This is my username - file dispute
                                </button>
                            </div>
                        @endif
                        @if ($disputeFiled)
                            <div class="gs-alert-success">
                                <p style="font-size: 13px; color: var(--gs-accent); margin: 0; line-height: 1.5;">Dispute filed. Your lecturer has been notified and will review this. You can continue without a GitHub username for now.</p>
                            </div>
                        @endif
                    </div>

                    <div class="gs-field-group">
                        <label for="personalEmail" class="gs-label">GitHub Email</label>
                        <p class="gs-hint">The email address linked to your GitHub account.</p>
                        <input
                            wire:model="personalEmail"
                            type="email"
                            id="personalEmail"
                            placeholder="you@example.com"
                            class="gs-input"
                        >
                        @error('personalEmail')
                            <p class="gs-error-text">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="gs-field-group">
                        <label for="gender" class="gs-label">Gender</label>
                        <select
                            wire:model="gender"
                            id="gender"
                            class="gs-select"
                        >
                            <option value="">Select...</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                        @error('gender')
                            <p class="gs-error-text">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="gs-btn-actions">
                        <button
                            type="button"
                            wire:click="$set('step', 'review')"
                            class="gs-btn-secondary"
                        >
                            Back
                        </button>
                        <button
                            type="submit"
                            class="gs-btn-primary"
                            wire:loading.attr="disabled"
                        >
                            <span wire:loading.remove wire:target="updateDetails">Save Details</span>
                            <span wire:loading wire:target="updateDetails" style="display: none; align-items: center; gap: 8px;">
                                <svg class="gs-spinner" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                </svg>
                                Saving...
                            </span>
                        </button>
                    </div>
                </form>

            {{-- Step: Updated --}}
            @elseif ($step === 'updated')
                <div class="gs-body" style="text-align: center;">
                    <div class="gs-icon-circle" style="background: var(--gs-accent-light);">
                        <svg style="width: 28px; height: 28px; color: var(--gs-accent);" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    </div>
                    <h2 class="gs-title" style="font-size: 22px; color: var(--gs-text); margin: 0;">Details Updated</h2>
                    <p style="font-size: 14px; color: var(--gs-text-muted); margin: 10px 0 0 0;">Your information has been saved successfully.</p>

                    @if ($backfillCount > 0)
                        <div class="gs-backfill-notice">
                            {{ $backfillCount }} lab grade{{ $backfillCount > 1 ? 's were' : ' was' }} automatically linked to your account.
                        </div>
                    @endif

                    <button
                        wire:click="resetLookup"
                        class="gs-btn-secondary"
                        style="margin-top: 24px;"
                    >
                        Verify Another Student
                    </button>
                </div>
            @endif

        </div>

        {{-- Footer --}}
        <p class="gs-footer">
            {{ config('app.name') }}
        </p>
    </div>
</div>
