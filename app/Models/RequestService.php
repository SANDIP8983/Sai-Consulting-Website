<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class RequestService extends Model {
 protected $fillable = ['service_id','professional_fee','gst_rate','government_charges','estimated_days','required_documents_snapshot','status','approved_at','rejected_at','decision_notes','decided_by'];
 protected function casts(): array { return ['professional_fee'=>'decimal:2','gst_rate'=>'decimal:2','government_charges'=>'decimal:2','estimated_days'=>'integer','required_documents_snapshot'=>'array','approved_at'=>'datetime','rejected_at'=>'datetime']; }
 public function request(): BelongsTo { return $this->belongsTo(CustomerRequest::class, 'request_id'); }
 public function service(): BelongsTo { return $this->belongsTo(Service::class); }
 public function decidedBy(): BelongsTo { return $this->belongsTo(User::class, 'decided_by'); }
 public function total(): float { return (float)$this->professional_fee + ((float)$this->professional_fee * (float)$this->gst_rate / 100) + (float)$this->government_charges; }
}
