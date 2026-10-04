<?php

namespace App\Rules\StoreRules;

/**
 * Used for any store without its own rules class (e.g. flyer-only stores
 * opted into Gemini extraction), so a new store never aborts processing.
 */
class DefaultRules extends BaseStoreRules
{
}
