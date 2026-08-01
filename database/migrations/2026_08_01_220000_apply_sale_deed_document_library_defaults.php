<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up(): void {
  foreach (['aadhaar','aadhar','pan card','passport','voter id','driving licence','driving license','bank passbook','bank statement','cheque book','check book','atm card','credit card','debit card','income proof','salary slip','identity proof','address proof','kyc'] as $term) {
   DB::table('service_required_documents')->whereRaw('LOWER(name_en) LIKE ?', ['%'.$term.'%'])->update(['is_active'=>false,'updated_at'=>now()]);
   DB::table('common_required_documents')->whereRaw('LOWER(name_en) LIKE ?', ['%'.$term.'%'])->update(['is_active'=>false,'updated_at'=>now()]);
  }
  $saleDeed=DB::table('services')->where(fn($query)=>$query->whereRaw('LOWER(name_en) = ?', ['sale deed'])->orWhere('slug','sale-deed'))->first();
  if(!$saleDeed){return;}
  $masters=DB::table('service_required_documents')->where('service_id',$saleDeed->id)->whereNotNull('common_required_document_id')->whereNull('service_required_documents.deleted_at')->join('common_required_documents','common_required_documents.id','=','service_required_documents.common_required_document_id')->whereNull('common_required_documents.deleted_at')->get(['common_required_documents.id','common_required_documents.name_en','common_required_documents.name_gu','common_required_documents.allowed_file_types','common_required_documents.max_upload_size_kb']);
  foreach(DB::table('services')->where('requires_property_documents',true)->pluck('id') as $serviceId){
   foreach($masters as $master){
    $configuration=DB::table('service_required_documents')->where('service_id',$serviceId)->where('common_required_document_id',$master->id)->whereNull('deleted_at')->first();
    if($configuration){DB::table('service_required_documents')->where('id',$configuration->id)->update(['is_active'=>true,'updated_at'=>now()]); continue;}
    DB::table('service_required_documents')->insert(['service_id'=>$serviceId,'common_required_document_id'=>$master->id,'name_en'=>$master->name_en,'name_gu'=>$master->name_gu,'is_mandatory'=>false,'is_active'=>true,'sort_order'=>999,'allowed_file_types'=>$master->allowed_file_types,'max_upload_size_kb'=>$master->max_upload_size_kb,'created_at'=>now(),'updated_at'=>now()]);
   }
  }
 }
 public function down(): void {}
};
