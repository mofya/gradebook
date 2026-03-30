<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Profile Info (read-only) --}}
        @php $student = $this->getStudent(); @endphp

        @if($student)
            <x-filament::section heading="Student Information">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Name</p>
                        <p class="text-sm text-gray-950 dark:text-white">{{ $student->first_name }} {{ $student->last_name }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Student ID</p>
                        <p class="text-sm text-gray-950 dark:text-white">{{ $student->student_id_number ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Institutional Email</p>
                        <p class="text-sm text-gray-950 dark:text-white">{{ $student->email }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Program</p>
                        <p class="text-sm text-gray-950 dark:text-white">{{ $student->program ?? 'N/A' }}</p>
                    </div>
                </div>
            </x-filament::section>

            {{-- Personal Email --}}
            <x-filament::section heading="Personal Email" description="Update the email address you use to log in.">
                <form wire:submit="updateProfile">
                    {{ $this->profileForm }}

                    <div class="mt-4">
                        <x-filament::button type="submit">
                            Update Email
                        </x-filament::button>
                    </div>
                </form>
            </x-filament::section>

            {{-- Change Password --}}
            @if($student->isRegistered())
                <x-filament::section heading="Change Password">
                    <form wire:submit="updatePassword">
                        {{ $this->passwordForm }}

                        <div class="mt-4">
                            <x-filament::button type="submit">
                                Update Password
                            </x-filament::button>
                        </div>
                    </form>
                </x-filament::section>
            @endif

            {{-- Sex --}}
            <x-filament::section heading="Sex">
                <form wire:submit="updateGender">
                    {{ $this->genderForm }}

                    <div class="mt-4">
                        <x-filament::button type="submit">
                            Update Sex
                        </x-filament::button>
                    </div>
                </form>
            </x-filament::section>

            {{-- GitHub Username --}}
            <x-filament::section heading="GitHub" description="Link your GitHub account for lab submission matching.">
                <form wire:submit="updateGithub">
                    {{ $this->githubForm }}

                    <div class="mt-4">
                        <x-filament::button type="submit">
                            Update GitHub
                        </x-filament::button>
                    </div>
                </form>
            </x-filament::section>
        @else
            <x-filament::section>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    No student record found for your account.
                </p>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
