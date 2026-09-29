<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ChatSetting;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;

class ChatSeeder extends Seeder
{
    public function run(): void
    {
        // Buscamos tu primer usuario administrador
        $admin = User::where('role', 'admin')->first();
        
        if (!$admin) {
            $this->command->warn('No he encontrado ningún usuario Admin. Crea uno primero en la BD.');
            return;
        }

        // ── Chat System ──
        ChatSetting::firstOrCreate([], [
            'forensic_keywords' => ['acoso', 'amenaza', 'denuncia', 'abuso', 'discriminación', 'soborno', 'fraude', 'robo', 'violencia', 'demanda'],
            'chat_policy_text' => 'POLÍTICA D\'ÚS DEL XAT CORPORATIU — CRT...',
            'require_policy_acceptance' => true
        ]);

        $general = ChatConversation::firstOrCreate(['name' => '📢 General'], [
            'type' => 'channel', 'created_by' => $admin->id, 'pinned' => true
        ]);
        
        $fisio = ChatConversation::firstOrCreate(['name' => '🏥 Fisioterapia'], [
            'type' => 'channel', 'created_by' => $admin->id, 'pinned' => false
        ]);
        
        $logo = ChatConversation::firstOrCreate(['name' => '🗣️ Logopèdia'], [
            'type' => 'channel', 'created_by' => $admin->id, 'pinned' => false
        ]);

        $allUsers = User::pluck('id')->toArray();
        $general->users()->syncWithoutDetaching($allUsers);

        $fisioUsers = User::where('job_profile', 'Fisioterapeuta')->pluck('id')->toArray();
        $fisioUsers[] = $admin->id;
        $fisio->users()->syncWithoutDetaching($fisioUsers);

        $logoUsers = User::where('job_profile', 'Logopeda')->pluck('id')->toArray();
        $logoUsers[] = $admin->id;
        $logo->users()->syncWithoutDetaching($logoUsers);

        ChatMessage::firstOrCreate(
            ['conversation_id' => $general->id, 'sender_id' => $admin->id],
            ['content' => 'Benvinguts al canal general de CRT! Utilitzeu aquest canal per a comunicacions d\'àmbit general.']
        );

        $this->command->info('Configuración de chats y canales creada correctamente!');
    }
}
