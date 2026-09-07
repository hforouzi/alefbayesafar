<?php

namespace App\Modules\TripPlanner\Service;

use App\Modules\TripPlanner\ValueObject\ConversationState;
use App\Modules\TripPlanner\ValueObject\TravelIntentUpdate;

/**
 * Turns one free-text user message into a structured, partial travel intent
 * update. Implementations must never fabricate fields the message did not
 * actually contain — an unmentioned field must stay null/false.
 */
interface TravelIntentInterpreterInterface
{
    public function interpret(string $message, ConversationState $state): TravelIntentUpdate;
}
