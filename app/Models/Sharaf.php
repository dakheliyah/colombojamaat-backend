<?php

namespace App\Models;

use App\Enums\SharafStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sharaf extends Model
{
    use HasFactory;

    protected $fillable = [
        'sharaf_definition_id',
        'rank',
        'capacity',
        'status',
        'hof_its',
        'token',
        'comments',
    ];

    protected $appends = ['hof_name'];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected $casts = [
        'rank' => 'integer',
        'capacity' => 'integer',
        'status' => SharafStatus::class,
    ];

    /**
     * Get the sharaf definition that owns the sharaf.
     */
    public function sharafDefinition(): BelongsTo
    {
        return $this->belongsTo(SharafDefinition::class);
    }

    /**
     * Get the sharaf members for the sharaf.
     */
    public function sharafMembers(): HasMany
    {
        return $this->hasMany(SharafMember::class);
    }

    /**
     * Get the sharaf clearances for the sharaf.
     */
    public function sharafClearances(): HasMany
    {
        return $this->hasMany(SharafClearance::class);
    }

    /**
     * Get the sharaf payments for the sharaf.
     */
    public function sharafPayments(): HasMany
    {
        return $this->hasMany(SharafPayment::class);
    }

    /**
     * Get the Head of Family from census.
     */
    public function hof(): BelongsTo
    {
        return $this->belongsTo(Census::class, 'hof_its', 'its_id');
    }

    /**
     * Get the clearance for this sharaf's HOF.
     */
    public function hofClearance()
    {
        return $this->hasOne(SharafClearance::class)
            ->where('hof_its', $this->hof_its);
    }

    /**
     * Get the HOF sharaf member.
     */
    public function hofMember()
    {
        return $this->hasOne(SharafMember::class, 'sharaf_id')
            ->where('its_id', $this->hof_its);
    }

    /**
     * Get the name of the HOF.
     */
    public function getHofNameAttribute(): ?string
    {
        // 1. Check if joined from query (attribute exists in the array)
        if (array_key_exists('hof_name', $this->attributes)) {
            return $this->attributes['hof_name'];
        }

        // 2. Fallback to census relationship
        if ($this->relationLoaded('hof') && $this->hof) {
            return $this->hof->name;
        }

        // 3. Fallback to sharafMembers collection
        if ($this->relationLoaded('sharafMembers')) {
            $member = $this->sharafMembers->firstWhere('its_id', $this->hof_its);
            return $member ? $member->name : null;
        }

        return null;
    }

    /**
     * Check if a specific payment is paid by payment name.
     *
     * @param string $paymentName
     * @return bool
     */
    public function hasPaymentPaid(string $paymentName): bool
    {
        $payment = $this->sharafPayments()
            ->whereHas('paymentDefinition', function ($query) use ($paymentName) {
                $query->where('name', $paymentName)
                    ->where('sharaf_definition_id', $this->sharaf_definition_id);
            })
            ->where('payment_status', true)
            ->first();

        return $payment !== null;
    }

    /**
     * Check if the sharaf can be confirmed.
     * A sharaf becomes confirmed only when ALL are true:
     * - Clearance for that sharaf's HOF is complete
     * - All required payments for the sharaf definition are paid
     */
    public function canBeConfirmed(): bool
    {
        $clearance = $this->hofClearance()->first();
        
        if (!$clearance || !$clearance->is_cleared) {
            return false;
        }

        // Get all payment definitions for this sharaf's definition
        $paymentDefinitions = PaymentDefinition::where('sharaf_definition_id', $this->sharaf_definition_id)->get();
        
        if ($paymentDefinitions->isEmpty()) {
            // If no payment definitions exist, only clearance is required
            return true;
        }

        // Check that all payment definitions have corresponding paid records
        foreach ($paymentDefinitions as $paymentDef) {
            $payment = $this->sharafPayments()
                ->where('payment_definition_id', $paymentDef->id)
                ->where('payment_status', true)
                ->first();
            
            if (!$payment) {
                return false;
            }
        }

        return true;
    }
}
