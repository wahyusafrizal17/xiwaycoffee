<?php

namespace App\Models;

use App\Enums\MembershipLevel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\AppliesFillableAttribute;

#[Fillable([
    'code', 'name', 'phone', 'email', 'birthday', 'gender', 'address',
    'membership_level', 'points', 'total_transaction', 'last_transaction_at', 'is_active',
])]
class Customer extends Model
{
    use AppliesFillableAttribute, SoftDeletes;

    protected function casts(): array
    {
        return [
            'birthday' => 'date',
            'membership_level' => MembershipLevel::class,
            'total_transaction' => 'decimal:2',
            'last_transaction_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function pointLedgers(): HasMany
    {
        return $this->hasMany(CustomerPoint::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toModalArray(): array
    {
        $genderLabels = ['male' => 'Laki-laki', 'female' => 'Perempuan', 'other' => 'Lainnya'];

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'phone' => $this->phone ?? '',
            'email' => $this->email ?? '',
            'birthday' => $this->birthday?->format('Y-m-d') ?? '',
            'birthday_label' => $this->birthday?->format('d/m/Y') ?? '—',
            'gender' => $this->gender ?? '',
            'gender_label' => $genderLabels[$this->gender ?? ''] ?? '—',
            'address' => $this->address ?? '',
            'membership_level' => $this->membership_level?->value ?? 'regular',
            'membership_label' => $this->membership_level?->label() ?? 'Regular',
            'is_active' => (bool) $this->is_active,
            'points_label' => number_format((int) $this->points),
            'total_transaction_label' => money($this->total_transaction),
            'last_transaction_label' => $this->last_transaction_at?->format('d/m/Y') ?? '—',
            'update_url' => route('customers.update', $this),
            'delete_url' => route('customers.destroy', $this),
            'points_url' => route('customers.points', $this),
            'rewards_url' => route('customers.rewards', $this),
        ];
    }

    public function refreshMembership(): void
    {
        $spending = (float) $this->total_transaction;
        $level = MembershipLevel::Regular;

        foreach ([MembershipLevel::Platinum, MembershipLevel::Gold, MembershipLevel::Silver] as $candidate) {
            if ($spending >= $candidate->minSpending()) {
                $level = $candidate;
                break;
            }
        }

        $this->update(['membership_level' => $level]);
    }
}
