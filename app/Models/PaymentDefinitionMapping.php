<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentDefinitionMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'sharaf_definition_mapping_id',
        'source_payment_definition_id',
        'target_payment_definition_id',
    ];

    /**
     * Get the sharaf definition mapping that owns this payment definition mapping.
     */
    public function definitionMapping(): BelongsTo
    {
        return $this->belongsTo(SharafDefinitionMapping::class);
    }

    /**
     * Get the source payment definition.
     */
    public function sourcePaymentDefinition(): BelongsTo
    {
        return $this->belongsTo(PaymentDefinition::class, 'source_payment_definition_id');
    }

    /**
     * Get the target payment definition.
     */
    public function targetPaymentDefinition(): BelongsTo
    {
        return $this->belongsTo(PaymentDefinition::class, 'target_payment_definition_id');
    }
}
