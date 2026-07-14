<?php

declare(strict_types=1);

namespace MadBox\GoogleConnect\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use MadBox\GoogleConnect\Models\GoogleOAuthSettings;

final class Team extends Model
{
    protected $guarded = [];

    /** @return HasOne<GoogleOAuthSettings, $this> */
    public function googleOAuthSettings(): HasOne
    {
        return $this->hasOne(GoogleOAuthSettings::class, 'team_id');
    }
}
