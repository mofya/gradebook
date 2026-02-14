# Gradebook

A gradebook application for managing academic years, courses, assessments, students, and grades. Built with Laravel and Filament.

## Tech Stack

- **PHP** 8.4
- **Laravel** 11
- **Filament** 5 (admin panel)
- **Livewire** 3
- **Tailwind CSS** 3
- **SQLite** (testing) / **MySQL** (production)
- **Maatwebsite Excel** (student CSV imports)

## Setup

```bash
# Install dependencies
composer install
npm install

# Environment
cp .env.example .env
php artisan key:generate

# Database
php artisan migrate --seed

# Build frontend assets
npm run build

# Start the development server
composer run dev
```

## Testing

```bash
# Run the full test suite
php artisan test --compact

# Run a specific test file
php artisan test --compact tests/Feature/Filament/CourseResourceTest.php

# Filter by test name
php artisan test --compact --filter=test_can_create_course
```

Tests use SQLite in-memory for speed.

## Project Structure

```
app/
├── Filament/
│   ├── Pages/              # Custom pages (ImportStudents, EnterGrades)
│   └── Resources/          # CRUD resources (Year, Course, Assessment, Student, Grade)
│       └── */RelationManagers/  # Relation managers for pivot tables
├── Imports/                # Excel import classes (StudentsImport)
└── Models/                 # Eloquent models
database/
├── factories/              # Model factories for all domain models
└── migrations/             # Database schema
tests/
├── Unit/Models/            # Model logic tests
├── Feature/Filament/       # Filament resource & page tests
└── Feature/Imports/        # Import class tests
```

## Key Features

- **Academic Years** — organize courses by year
- **Courses** — belong to a year, have assessments and enrolled students
- **Assessments** — weighted components of a course grade
- **Students** — enroll in courses via many-to-many relationship
- **Grades** — per-student, per-assessment scores with weighted total calculation
- **Enter Grades** — batch grade entry page per course/assessment
- **Import Students** — bulk import students from Excel/CSV files
