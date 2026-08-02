<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class RequestCaseActionHistory extends Model
{
    protected $fillable = ['request_id','action','from_status','to_status','reason','internal_note','customer_remark','performed_by'];
    public function performedBy(): BelongsTo { return $this->belongsTo(User::class, 'performed_by'); }
}
