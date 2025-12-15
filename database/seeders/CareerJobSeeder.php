<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CareerJob;
use App\Models\User;

class CareerJobSeeder extends Seeder
{
    public function run(): void
    {
        $employer = User::firstOrCreate(
            ['email' => 'employer@example.com'],
            [
                'name' => 'Inclusive Employer',
                'password' => bcrypt('password'),
            ]
        );

        $jobs = [
            [
                'title' => 'Senior Frontend Developer (React)',
                'description' => 'Разработка доступных интерфейсов для нашего флагманского продукта. Требуется глубокое знание React, TypeScript и WCAG 2.1. Мы ценим чистый код и внимание к деталям.',
                'company_name' => 'Inclusive Tech',
                'location' => 'Алматы',
                'salary_from' => 600000,
                'salary_to' => 900000,
                'currency' => 'KZT',
                'experience' => 'between3And6',
                'schedule' => 'remote',
                'employment' => 'full',
                'accessibility_features' => ['remote_only', 'screen_reader_compatible'],
            ],
            [
                'title' => 'Специалист технической поддержки (Жестовый язык)',
                'description' => 'Оказание помощи пользователям через видеосвязь на жестовом языке. Работа с тикетами и чатами. Обучение продукту предоставляется.',
                'company_name' => 'Visual Mind Support',
                'location' => 'Алматы',
                'salary_from' => 250000,
                'salary_to' => 350000,
                'currency' => 'KZT',
                'experience' => 'between1And3',
                'schedule' => 'shift',
                'employment' => 'full',
                'accessibility_features' => ['sign_language_interpreter', 'wheelchair_accessible'],
            ],
            [
                'title' => 'UX/UI Дизайнер (Стажировка)',
                'description' => 'Ищем талантливого начинающего дизайнера. Вы будете помогать в создании макетов и прототипов. Менторство со стороны арт-директора.',
                'company_name' => 'Creative Studio',
                'location' => 'Астана',
                'salary_from' => 100000,
                'salary_to' => 150000,
                'currency' => 'KZT',
                'experience' => 'noExperience',
                'schedule' => 'flexible',
                'employment' => 'probation',
                'accessibility_features' => ['wheelchair_accessible', 'screen_reader_compatible'],
            ],
            [
                'title' => 'Python Backend Developer',
                'description' => 'Разработка микросервисов на FastAPI. Работа в распределенной команде. Участие в проектировании архитектуры.',
                'company_name' => 'DataFlow',
                'location' => 'Караганда',
                'salary_from' => 400000,
                'salary_to' => 600000,
                'currency' => 'KZT',
                'experience' => 'between1And3',
                'schedule' => 'remote',
                'employment' => 'full',
                'accessibility_features' => ['remote_only'],
            ],
            [
                'title' => 'Контент-менеджер / Копирайтер',
                'description' => 'Написание статей и постов для блога. Адаптация контента для скринридеров (альтернативный текст, заголовки).',
                'company_name' => 'MediaGroup',
                'location' => 'Алматы',
                'salary_from' => 200000,
                'salary_to' => null,
                'currency' => 'KZT',
                'experience' => 'between1And3',
                'schedule' => 'fullDay',
                'employment' => 'part',
                'accessibility_features' => ['wheelchair_accessible', 'screen_reader_compatible'],
            ],
            [
                'title' => 'Бухгалтер (Удаленно)',
                'description' => 'Ведение первичной документации, сдача отчетности. Работа в 1С.',
                'company_name' => 'FinService',
                'location' => 'Шымкент',
                'salary_from' => 180000,
                'salary_to' => 250000,
                'currency' => 'KZT',
                'experience' => 'moreThan6',
                'schedule' => 'remote',
                'employment' => 'part',
                'accessibility_features' => ['remote_only', 'wheelchair_accessible'],
            ],
            [
                'title' => 'Project Manager',
                'description' => 'Управление IT-проектами, координация команды разработки. Опыт работы с Agile/Scrum.',
                'company_name' => 'SoftSolutions',
                'location' => 'Алматы',
                'salary_from' => 500000,
                'salary_to' => 800000,
                'currency' => 'KZT',
                'experience' => 'between3And6',
                'schedule' => 'fullDay',
                'employment' => 'full',
                'accessibility_features' => ['wheelchair_accessible'],
            ],
            [
                'title' => 'Оператор Call-центра',
                'description' => 'Прием входящих звонков, консультация клиентов. Гибкий график, возможно совмещение с учебой.',
                'company_name' => 'TelecomKZ',
                'location' => 'Астана',
                'salary_from' => 120000,
                'salary_to' => 180000,
                'currency' => 'KZT',
                'experience' => 'noExperience',
                'schedule' => 'shift',
                'employment' => 'part',
                'accessibility_features' => ['wheelchair_accessible'],
            ],
            [
                'title' => 'HR Менеджер',
                'description' => 'Поиск и подбор персонала, проведение собеседований. Организация корпоративных мероприятий.',
                'company_name' => 'HR Partners',
                'location' => 'Алматы',
                'salary_from' => 300000,
                'salary_to' => 450000,
                'currency' => 'KZT',
                'experience' => 'between1And3',
                'schedule' => 'fullDay',
                'employment' => 'full',
                'accessibility_features' => ['wheelchair_accessible'],
            ],
            [
                'title' => 'QA Engineer (Manual)',
                'description' => 'Ручное тестирование веб-приложений и мобильных приложений. Написание тест-кейсов.',
                'company_name' => 'QualityFirst',
                'location' => 'Remote',
                'salary_from' => 250000,
                'salary_to' => 350000,
                'currency' => 'KZT',
                'experience' => 'noExperience',
                'schedule' => 'remote',
                'employment' => 'full',
                'accessibility_features' => ['remote_only', 'screen_reader_compatible'],
            ],
            [
                'title' => 'Архитектор ПО',
                'description' => 'Проектирование высоконагруженных систем. Менторство команды.',
                'company_name' => 'BigSystem',
                'location' => 'Астана',
                'salary_from' => 1200000,
                'salary_to' => 1800000,
                'currency' => 'KZT',
                'experience' => 'moreThan6',
                'schedule' => 'fullDay',
                'employment' => 'full',
                'accessibility_features' => ['wheelchair_accessible'],
            ],
            [
                'title' => 'Волонтер на мероприятие',
                'description' => 'Помощь в организации благотворительного забега. Выдача номеров, навигация участников.',
                'company_name' => 'CharityFund',
                'location' => 'Алматы',
                'salary_from' => null,
                'salary_to' => null,
                'currency' => 'KZT',
                'experience' => 'noExperience',
                'schedule' => 'flexible',
                'employment' => 'volunteer',
                'accessibility_features' => ['sign_language_interpreter'],
            ],
        ];

        foreach ($jobs as $job) {
            CareerJob::create(array_merge($job, [
                'employer_id' => $employer->id,
                'status' => 'active',
                'published_at' => now(),
            ]));
        }
    }
}
