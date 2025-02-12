<?php

namespace Namu\WireChat\Jobs;

use App\Jobs\CreateDatabaseNotificationsJob;
use App\Models\Tenant;
use App\Notifications\CauserDatabaseNotification;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\WithoutRelations;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Namu\WireChat\Events\NotifyParticipant;
use Namu\WireChat\Facades\WireChat;
use Namu\WireChat\Models\Message;
use Namu\WireChat\Models\Participant;

class NotifyParticipants implements ShouldQueue
{
    use Batchable,Dispatchable, InteractsWithQueue, Queueable;
    use SerializesModels {
        __unserialize as public baseUnserialize;
    }
    // We need this because the job is not aware of the tenant context.
    public function __unserialize(array $values)
    {
        $tenantId = optional($values['tenant'])->id;
        $tenant = tenancy()->initialize(Tenant::find($tenantId));
        $this->baseUnserialize($values);
    }

    /**
     * Set a maximum time limit of 60 seconds for the job.
     * Because we don't want users getting old notifications
     */
    public int $timeout = 60;

    public int $retry_after = 65;

    public int $tries = 1;

    protected $auth;

    public function __construct(
        public Model $conversation,
        #[WithoutRelations]
        public Message $message,
        public Tenant $tenant)
    {
        $this->onQueue(WireChat::notificationsQueue());
    }

    public function handle(): void
    {
        $this->auth = $this->message->sendable;
        // Check if the message is too old
        $messageAgeInSeconds = now()->diffInSeconds($this->message->created_at);

        //delete the job if it is greater then 60 seconds
        if ($messageAgeInSeconds > 60) {
            // Delete the job and stop further processing
            //$this->fail();
            $this->delete();

            return;
        }

        /**
         * Fetch participants, ordered by `last_active_at` in descending order,
         * so that the most recently active participants are notified first. */
        Participant::where('conversation_id', $this->conversation->id)
        //exclude current user
            ->where(function ($query) {
                $query->where('participantable_id', '!=', $this->auth->id)
                    ->where('participantable_type', get_class($this->auth));
            })
            ->latest('last_active_at') // Prioritize active participants
            ->chunk(50, function ($participants) {
                foreach ($participants as $key => $participant) {
                    broadcast(new NotifyParticipant($participant, $this->message));
                    $this->participantNotification($participant, $this->message);
                }
            });

    }

    private function participantNotification($participant, Message $message)
    {
        $user = $participant->participantable;
        if (! $user) {
            return;
        }

        $messageBody = $message->body ?: __('wirechat.Sent an attachment');
        if ($this->conversation->isGroup()) {
            $group = $this->conversation->group->group;
            $messageUrl = route('filament.tenant.resources.groups.view', ['record' => $group->slug]) . '?active_tab=chat_tab';
        } else {
            $messageUrl = route(WireChat::viewRouteName(), [$message->conversation->ulid]);
        }

        $notification = CreateDatabaseNotificationsJob::createNotification($this->auth, 1, $messageBody, $messageUrl);

        $causer = [
            'causer_type' => Message::class,
            'causer_id' => $message->id,
        ];

        if ($user->online && $user->active_chat == $this->conversation->id) {
            return; // User is in the chat currently
        }

        \Log::info("Notify user {$user->name} who has active chat {$user->active_chat} vs current conversation id {$this->conversation->id}");

        if ($user->online && $user->browser_tab_active) {
            $user->notify($notification->toBroadcast()); // In app popup
        } else {
            // Notification bell
            $user->notify(new CauserDatabaseNotification($notification, $causer));
        }
    }
}
