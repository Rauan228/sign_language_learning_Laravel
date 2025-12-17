<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ConnectCategory;
use Illuminate\Support\Str;

class ConnectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Жизнь с инвалидностью',
                'icon' => 'wheelchair',
                'description' => 'Обсуждение повседневной жизни, советы и поддержка.'
            ],
            [
                'name' => 'Жестовый язык',
                'icon' => 'hand',
                'description' => 'Все о жестовом языке, изучение и практика.'
            ],
            [
                'name' => 'Работа и обучение',
                'icon' => 'briefcase',
                'description' => 'Карьера, поиск работы, доступное образование.'
            ],
            [
                'name' => 'Психологическая поддержка',
                'icon' => 'brain',
                'description' => 'Взаимопомощь, советы психологов и ментальное здоровье.'
            ],
            [
                'name' => 'Здоровье',
                'icon' => 'heart',
                'description' => 'Вопросы здоровья, реабилитации и медицины.'
            ],
            [
                'name' => 'Технологии и адаптация',
                'icon' => 'chip',
                'description' => 'Гаджеты, приложения и технологии для доступности.'
            ],
            [
                'name' => 'Истории успеха',
                'icon' => 'star',
                'description' => 'Вдохновляющие примеры и личные достижения.'
            ],
            [
                'name' => 'Просто поговорить',
                'icon' => 'chat',
                'description' => 'Свободное общение на любые темы.'
            ],
        ];

        foreach ($categories as $cat) {
            ConnectCategory::firstOrCreate(
                ['name' => $cat['name']],
                [
                    'slug' => Str::slug($cat['name']),
                    'icon' => $cat['icon'],
                    'description' => $cat['description'],
                ]
            );
        }
    }
}
