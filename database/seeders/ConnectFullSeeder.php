<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\ConnectCategory;
use App\Models\ConnectPost;
use App\Models\ConnectProfessional;
use App\Models\ConnectService;
use App\Models\ConnectSchedule;
use App\Models\ConnectBooking;
use App\Models\ConnectReview;
use App\Models\ConnectLike;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ConnectFullSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Users (Authors & Professionals)
        $users = $this->createUsers();

        // 2. Ensure Categories Exist
        $this->call(ConnectSeeder::class);
        $categories = ConnectCategory::all();

        // 3. Create Professionals
        $professionals = $this->createProfessionals($users);

        // 4. Create Posts (Threads)
        $posts = $this->createPosts($users, $categories);

        // 5. Create Comments & Likes (as Replies)
        $this->createCommentsAndLikes($users, $posts);

        // 6. Create Services, Schedules, Bookings & Reviews
        $this->createProfessionalData($users, $professionals);
    }

    private function createUsers()
    {
        $users = [];
        
        // Admin / Main User
        $users[] = User::firstOrCreate(
            ['email' => 'admin@visualmind.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]
        );

        // Professionals candidates
        $professionalsData = [
            ['name' => 'Елена Психолог', 'email' => 'elena@psy.com'],
            ['name' => 'Иван Сурдопереводчик', 'email' => 'ivan@sign.com'],
            ['name' => 'Мария Юрист', 'email' => 'maria@law.com'],
            ['name' => 'Доктор Хаус', 'email' => 'house@med.com'],
        ];

        foreach ($professionalsData as $p) {
            $users[] = User::firstOrCreate(
                ['email' => $p['email']],
                [
                    'name' => $p['name'],
                    'password' => Hash::make('password'),
                    'role' => 'student', 
                ]
            );
        }

        // Regular users
        for ($i = 1; $i <= 10; $i++) {
            $users[] = User::firstOrCreate(
                ['email' => "user{$i}@example.com"],
                [
                    'name' => "User {$i}",
                    'password' => Hash::make('password'),
                    'role' => 'student',
                ]
            );
        }

        return collect($users);
    }

    private function createProfessionals($users)
    {
        $professionals = [];
        
        // Elena - Psychologist
        $elena = $users->firstWhere('email', 'elena@psy.com');
        $professionals[] = ConnectProfessional::firstOrCreate(
            ['user_id' => $elena->id],
            [
                'title' => 'Клинический психолог',
                'specialization' => 'Психология',
                'bio' => 'Помогаю справиться с тревогой, депрессией и принятием себя. Опыт работы 10 лет.',
                'languages' => ['ru', 'en'],
                'price_per_hour' => 5000,
                'rating' => 4.9,
                'reviews_count' => 15,
                'is_verified' => true,
            ]
        );

        // Ivan - Sign Language Interpreter
        $ivan = $users->firstWhere('email', 'ivan@sign.com');
        $professionals[] = ConnectProfessional::firstOrCreate(
            ['user_id' => $ivan->id],
            [
                'title' => 'Сертифицированный переводчик РЖЯ',
                'specialization' => 'Сурдоперевод',
                'bio' => 'Перевод онлайн встреч, консультаций и документов. Родной язык - жестовый.',
                'languages' => ['ru', 'rsl'],
                'price_per_hour' => 3000,
                'rating' => 5.0,
                'reviews_count' => 42,
                'is_verified' => true,
            ]
        );

        // Maria - Lawyer
        $maria = $users->firstWhere('email', 'maria@law.com');
        $professionals[] = ConnectProfessional::firstOrCreate(
            ['user_id' => $maria->id],
            [
                'title' => 'Юрист по правам инвалидов',
                'specialization' => 'Юриспруденция',
                'bio' => 'Консультации по льготам, трудоустройству и доступной среде.',
                'languages' => ['ru'],
                'price_per_hour' => 4500,
                'rating' => 4.7,
                'reviews_count' => 8,
                'is_verified' => true,
            ]
        );

        return $professionals;
    }

    private function createPosts($users, $categories)
    {
        $posts = [];
        $titles = [
            'Как найти работу глухому программисту?',
            'Лучшие приложения для перевода речи в текст',
            'Моя история принятия диагноза',
            'Нужен совет по выбору слухового аппарата',
            'Давайте обсудим доступность метро',
            'Ищу друзей для общения на РЖЯ',
            'Юридическая помощь при отказе в ИПР',
            'Смешные случаи из жизни',
            'Где учиться на дизайнера с нарушением слуха?',
            'Психологические барьеры в общении',
        ];

        foreach ($titles as $index => $title) {
            $user = $users->random();
            $category = $categories->random();
            $type = collect(['question', 'experience', 'discussion', 'support'])->random();
            
            $post = ConnectPost::create([
                'user_id' => $user->id,
                'category_id' => $category->id,
                'title' => $title,
                'content' => "Это пример содержания поста для темы '{$title}'. Здесь может быть длинный текст с описанием проблемы или истории. " . Str::random(100),
                'type' => $type,
                'is_anonymous' => rand(0, 1),
                'tags' => ['help', 'advice', 'life'],
                'views' => rand(10, 500),
                'likes_count' => 0,
                'reply_count' => 0,
                'created_at' => Carbon::now()->subDays(rand(1, 30)),
                'parent_id' => null,
                'depth' => 0,
            ]);
            
            // Set root and path for new post
            $post->root_thread_id = $post->id;
            $post->path = (string) $post->id;
            $post->save();

            $posts[] = $post;
        }

        return $posts;
    }

    private function createCommentsAndLikes($users, $posts)
    {
        foreach ($posts as $post) {
            // Add likes to root post
            $likesCount = rand(0, 10);
            for ($i = 0; $i < $likesCount; $i++) {
                $user = $users->random();
                if (!$post->likes()->where('user_id', $user->id)->exists()) {
                    $post->likes()->create(['user_id' => $user->id]);
                    $post->increment('likes_count');
                }
            }

            // Add replies (was comments)
            $repliesCount = rand(0, 5);
            for ($i = 0; $i < $repliesCount; $i++) {
                $user = $users->random();
                
                // Create Reply
                $reply = ConnectPost::create([
                    'user_id' => $user->id,
                    'category_id' => $post->category_id,
                    'title' => null,
                    'content' => 'Это очень важная тема! Спасибо, что подняли ее. ' . Str::random(20),
                    'type' => $post->type,
                    'is_anonymous' => rand(0, 1),
                    'parent_id' => $post->id,
                    'root_thread_id' => $post->id,
                    'depth' => 1,
                    // path set later
                ]);
                $reply->path = $post->path . '/' . $reply->id;
                $reply->save();
                
                $post->increment('reply_count');

                // Nested Reply (Reply to Reply)
                if (rand(0, 1)) {
                    $nestedUser = $users->random();
                    $nestedReply = ConnectPost::create([
                        'user_id' => $nestedUser->id,
                        'category_id' => $post->category_id,
                        'title' => null,
                        'content' => 'Согласен с комментатором выше.',
                        'type' => $post->type,
                        'is_anonymous' => 0,
                        'parent_id' => $reply->id,
                        'root_thread_id' => $post->id,
                        'depth' => 2,
                    ]);
                    $nestedReply->path = $reply->path . '/' . $nestedReply->id;
                    $nestedReply->save();
                    
                    $reply->increment('reply_count');
                    $post->increment('reply_count'); // Increment root count too
                }
            }
        }
    }

    private function createProfessionalData($users, $professionals)
    {
        foreach ($professionals as $prof) {
            // 1. Services
            $service = ConnectService::create([
                'professional_id' => $prof->id,
                'type' => 'online',
                'name' => 'Индивидуальная консультация',
                'description' => 'Разбор вашей ситуации и рекомендации.',
                'duration_minutes' => 60,
                'price' => $prof->price_per_hour,
                'max_participants' => 1,
            ]);

            // 2. Schedule (Next 7 days)
            for ($d = 0; $d < 7; $d++) {
                $date = Carbon::now()->addDays($d);
                // 3 slots per day
                for ($h = 10; $h <= 14; $h += 2) {
                    ConnectSchedule::create([
                        'professional_id' => $prof->id,
                        'start_time' => $date->copy()->setHour($h)->setMinute(0),
                        'end_time' => $date->copy()->setHour($h + 1)->setMinute(0),
                        'is_booked' => false,
                    ]);
                }
            }

            // 3. Reviews & Bookings
            $reviewers = $users->whereNotIn('id', [$prof->user_id])->random(3);
            
            foreach ($reviewers as $reviewer) {
                // Create a past booking
                $booking = ConnectBooking::create([
                    'user_id' => $reviewer->id,
                    'professional_id' => $prof->id,
                    'service_id' => $service->id,
                    'schedule_id' => null, // Past booking, schedule might be gone or simplified
                    'status' => 'completed',
                    'is_anonymous' => false,
                    'notes' => 'Нужна помощь срочно',
                ]);

                // Create review
                ConnectReview::create([
                    'user_id' => $reviewer->id,
                    'professional_id' => $prof->id,
                    'booking_id' => $booking->id,
                    'rating' => rand(4, 5),
                    'comment' => 'Отличный специалист! Очень помог разобраться в вопросе.',
                    'is_anonymous' => rand(0, 1),
                ]);
            }
        }
    }
}
