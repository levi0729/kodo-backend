<?php

namespace App\Services;

use App\Models\Message;
use App\Models\MessageMention;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Support\Collection;

class MentionParser
{
    /**
     * Regex to extract @username tokens from free-form message text.
     * Usernames may contain letters, digits, underscore and dot.
     */
    private const MENTION_REGEX = '/(?<![\w.])@([a-zA-Z0-9._]{2,50})/';

    /**
     * Extract unique, lowercased usernames referenced via @mentions.
     *
     * @return array<int,string>
     */
    public static function extractUsernames(?string $content): array
    {
        if (! $content) {
            return [];
        }

        preg_match_all(self::MENTION_REGEX, $content, $matches);

        return collect($matches[1] ?? [])
            ->map(fn ($u) => strtolower($u))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Look up users for a set of @usernames, excluding the sender.
     *
     * @param  array<int,string>  $usernames
     */
    public static function resolveUsers(array $usernames, int $senderId): Collection
    {
        if (empty($usernames)) {
            return collect();
        }

        return User::whereIn('username', $usernames)
            ->where('id', '!=', $senderId)
            ->get();
    }

    /**
     * Persist MessageMention rows + Notification rows for an Eloquent Message.
     * Used by ChannelController and ConversationController.
     */
    public static function handleMessage(Message $message, ?string $title = null): void
    {
        $usernames = self::extractUsernames($message->content);
        $users     = self::resolveUsers($usernames, (int) $message->sender_id);

        if ($users->isEmpty()) {
            return;
        }

        $sender = $message->sender ?? User::find($message->sender_id);
        $body   = self::buildBody($sender, $message->content);

        foreach ($users as $user) {
            MessageMention::firstOrCreate([
                'message_id'    => $message->id,
                'mention_type'  => 'user',
                'mentioned_id'  => $user->id,
            ]);

            if (self::isDndActive($user->id)) {
                continue;
            }

            Notification::create([
                'user_id'           => $user->id,
                'notification_type' => 'mention',
                'actor_id'          => $message->sender_id,
                'channel_id'        => $message->channel_id,
                'message_id'        => $message->id,
                'title'             => $title ?? self::defaultTitle($sender),
                'body'              => $body,
                'action_url'        => self::buildActionUrl($message),
                'is_read'           => false,
            ]);
        }
    }

    /**
     * Persist Notification rows only (no MessageMention) for legacy ChatRoom messages.
     * MessageMention.message_id references messages.id, not chat_rooms.id, so we
     * skip persisting mentions there and only deliver the notification.
     *
     * @param  array<string,mixed>  $context  keys: team_id, action_url
     */
    public static function handleChatRoom(string $content, int $senderId, array $context = []): void
    {
        $usernames = self::extractUsernames($content);
        $users     = self::resolveUsers($usernames, $senderId);

        if ($users->isEmpty()) {
            return;
        }

        $sender = User::find($senderId);
        $body   = self::buildBody($sender, $content);

        foreach ($users as $user) {
            if (self::isDndActive($user->id)) {
                continue;
            }

            Notification::create([
                'user_id'           => $user->id,
                'notification_type' => 'mention',
                'actor_id'          => $senderId,
                'team_id'           => $context['team_id'] ?? null,
                'title'             => self::defaultTitle($sender),
                'body'              => $body,
                'action_url'        => $context['action_url'] ?? null,
                'is_read'           => false,
            ]);
        }
    }

    /**
     * Check if a user currently has Do Not Disturb active.
     */
    private static function isDndActive(int $userId): bool
    {
        $settings = UserSetting::where('user_id', $userId)->first();
        if (! $settings || ! $settings->dnd_enabled) {
            return false;
        }

        $start = $settings->dnd_start_time;
        $end   = $settings->dnd_end_time;
        if (! $start || ! $end) {
            return true; // DND enabled but no time window = always on
        }

        $now = now()->format('H:i');
        // Handle overnight ranges (e.g. 22:00 – 07:00)
        if ($start <= $end) {
            return $now >= $start && $now <= $end;
        }

        return $now >= $start || $now <= $end;
    }

    private static function defaultTitle(?User $sender): string
    {
        $name = $sender?->display_name ?: ($sender?->username ?? 'Someone');

        return "{$name} mentioned you";
    }

    private static function buildBody(?User $sender, string $content): string
    {
        $snippet = mb_substr(trim($content), 0, 140);

        return $snippet !== '' ? $snippet : '…';
    }

    private static function buildActionUrl(Message $message): ?string
    {
        if ($message->channel_id) {
            return "/teams?channel={$message->channel_id}&message={$message->id}";
        }

        if ($message->conversation_id) {
            return "/messages?conversation={$message->conversation_id}&message={$message->id}";
        }

        return null;
    }
}
