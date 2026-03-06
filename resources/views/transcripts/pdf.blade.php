<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Academic Transcript</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            color: #333;
            margin: 40px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #1a365d;
            padding-bottom: 20px;
        }
        .header h1 {
            font-size: 22px;
            color: #1a365d;
            margin: 0;
        }
        .header h2 {
            font-size: 16px;
            color: #4a5568;
            margin: 5px 0;
            font-weight: normal;
        }
        .header h3 {
            font-size: 14px;
            color: #718096;
            margin: 5px 0;
            font-weight: normal;
        }
        .student-info {
            margin-bottom: 20px;
            background: #f7fafc;
            padding: 15px;
            border-radius: 4px;
        }
        .student-info p {
            margin: 4px 0;
        }
        .student-info strong {
            display: inline-block;
            width: 120px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th {
            background-color: #1a365d;
            color: white;
            padding: 10px 8px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
        }
        td {
            padding: 8px;
            border-bottom: 1px solid #e2e8f0;
        }
        tr:nth-child(even) {
            background-color: #f7fafc;
        }
        .summary {
            margin-top: 20px;
            padding: 15px;
            background: #ebf8ff;
            border-left: 4px solid #1a365d;
        }
        .summary p {
            margin: 5px 0;
            font-size: 14px;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 10px;
            color: #a0aec0;
            border-top: 1px solid #e2e8f0;
            padding-top: 15px;
        }
        .grade-a { color: #276749; font-weight: bold; }
        .grade-b { color: #2b6cb0; font-weight: bold; }
        .grade-c { color: #975a16; font-weight: bold; }
        .grade-d { color: #c05621; font-weight: bold; }
        .grade-f { color: #c53030; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>THE UNIVERSITY OF ZAMBIA</h1>
        <h2>Office of the Registrar</h2>
        <h3>Official Academic Transcript</h3>
    </div>

    <div class="student-info">
        <p><strong>Student Name:</strong> {{ $student->first_name }} {{ $student->last_name }}</p>
        <p><strong>Email:</strong> {{ $student->email }}</p>
        <p><strong>Student ID:</strong> {{ $student->student_id_number }}</p>
        <p><strong>Date Issued:</strong> {{ $generated_at }}</p>
    </div>

    @if(count($courses) > 0)
        <table>
            <thead>
                <tr>
                    <th>Course Code</th>
                    <th>Course Name</th>
                    <th>Credits</th>
                    <th>Mark (%)</th>
                    <th>Grade</th>
                    <th>Points</th>
                </tr>
            </thead>
            <tbody>
                @foreach($courses as $course)
                    <tr>
                        <td>{{ $course['course_code'] }}</td>
                        <td>{{ $course['course_name'] }}</td>
                        <td>{{ $course['credits'] }}</td>
                        <td>{{ $course['mark'] !== null ? number_format($course['mark'], 1) : 'N/A' }}</td>
                        <td>
                            @if($course['letter_grade'])
                                @php
                                    $gradeClass = match(true) {
                                        str_starts_with($course['letter_grade'], 'A') => 'grade-a',
                                        str_starts_with($course['letter_grade'], 'B') => 'grade-b',
                                        str_starts_with($course['letter_grade'], 'C') => 'grade-c',
                                        str_starts_with($course['letter_grade'], 'D') => 'grade-d',
                                        default => 'grade-f',
                                    };
                                @endphp
                                <span class="{{ $gradeClass }}">{{ $course['letter_grade'] }}</span>
                            @else
                                N/A
                            @endif
                        </td>
                        <td>{{ $course['grade_points'] !== null ? number_format($course['grade_points'], 1) : 'N/A' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="summary">
            <p><strong>Cumulative GPA (CGPA):</strong> {{ number_format($cumulative_gpa, 2) }} / 4.00</p>
        </div>
    @else
        <p>No course records found for this student.</p>
    @endif

    <div class="footer">
        <p>This is a computer-generated transcript issued by The University of Zambia.</p>
        <p>Generated on {{ $generated_at }}</p>
    </div>
</body>
</html>
