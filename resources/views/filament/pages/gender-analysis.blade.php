<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        @if($reportData)
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 p-5 dark:bg-gray-900 dark:ring-white/10">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                    {{ $reportData['course_code'] }} — {{ $reportData['course_name'] }}
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ $reportData['semester'] }}</p>
            </div>

            @forelse($reportData['analysis'] as $gender => $data)
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
                    <div class="px-5 py-3 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="font-semibold text-gray-800 dark:text-white/90">{{ $gender }}</h3>
                    </div>

                    <div class="p-5">
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 mb-5">
                            <div class="text-center">
                                <p class="text-xs text-gray-500 dark:text-gray-400">Enrolled</p>
                                <p class="text-xl font-bold text-gray-800 dark:text-white/90">{{ $data['total'] }}</p>
                            </div>
                            <div class="text-center">
                                <p class="text-xs text-gray-500 dark:text-gray-400">Average</p>
                                <p class="text-xl font-bold text-gray-800 dark:text-white/90">{{ number_format($data['average'], 1) }}</p>
                            </div>
                            <div class="text-center">
                                <p class="text-xs text-gray-500 dark:text-gray-400">Pass Rate</p>
                                <p class="text-xl font-bold {{ $data['pass_rate'] >= 50 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">{{ number_format($data['pass_rate'], 1) }}%</p>
                            </div>
                            <div class="text-center">
                                <p class="text-xs text-gray-500 dark:text-gray-400">Range</p>
                                <p class="text-xl font-bold text-gray-800 dark:text-white/90">{{ number_format($data['lowest'], 0) }}–{{ number_format($data['highest'], 0) }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 mb-4">
                            <div class="text-center rounded-lg bg-green-50 p-3 dark:bg-green-500/10">
                                <p class="text-xs text-green-700 dark:text-green-400">Passed</p>
                                <p class="text-lg font-bold text-green-700 dark:text-green-400">{{ $data['pass_count'] }}</p>
                            </div>
                            <div class="text-center rounded-lg bg-red-50 p-3 dark:bg-red-500/10">
                                <p class="text-xs text-red-700 dark:text-red-400">Failed</p>
                                <p class="text-lg font-bold text-red-700 dark:text-red-400">{{ $data['fail_count'] }}</p>
                            </div>
                            <div class="text-center rounded-lg bg-gray-50 p-3 dark:bg-gray-700">
                                <p class="text-xs text-gray-600 dark:text-gray-400">Graded</p>
                                <p class="text-lg font-bold text-gray-800 dark:text-white/90">{{ $data['graded'] }}</p>
                            </div>
                        </div>

                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Grade Distribution</h4>
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        @foreach($data['distribution'] as $grade => $count)
                                            <th class="px-3 py-2 text-center font-medium text-gray-500 dark:text-gray-400">{{ $grade }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        @foreach($data['distribution'] as $grade => $count)
                                            <td class="px-3 py-2 text-center font-semibold text-gray-800 dark:text-white/90">{{ $count }}</td>
                                        @endforeach
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @empty
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 p-5 text-center dark:bg-gray-900 dark:ring-white/10">
                    <p class="text-sm text-gray-500 dark:text-gray-400">No enrollment data available.</p>
                </div>
            @endforelse
        @endif
    </div>
</x-filament-panels::page>
