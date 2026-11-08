<?php
namespace OpenEMR\Common\Database {
 class QueryUtils { public static function querySingleRow($sql, $params=[]) { return null; } }
}
namespace {
spl_autoload_register(function($class) { $prefix='OpenEMR\\Modules\\TreatmentPlanner\\'; if(strpos($class,$prefix)===0) require dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php'; });
use OpenEMR\Modules\TreatmentPlanner\Services\{AssessmentEvidenceService, AssessmentProfileService, ReviewChecklistService, ProgressService};
$count=0;
function verifyClinical($ok,$message) { if(!$ok) throw new RuntimeException($message); $GLOBALS['count']++; }
function denyClinical($fn) { try{$fn();}catch(\OpenEMR\Modules\TreatmentPlanner\Exceptions\UserFacingException $e){$GLOBALS['count']++;return;}throw new RuntimeException('Expected rejection'); }
$fields=[];foreach(AssessmentEvidenceService::IMPORTANT as $key=>$label) $fields[$key]=['value'=>'Documented','title'=>$label];
$fields['suicide_current']['value']='No current suicidal ideation';
$fields['current_medications']['value']='0';
$s=AssessmentEvidenceService::snapshot($fields,['form'=>'LBF_TEST','encounter'=>20]);
verifyClinical(!$s['missing'] && count($s['sources'])===6,'Documented negative and zero must not be missing');
AssessmentEvidenceService::assertReady($s,false);
$firstHash=AssessmentEvidenceService::fingerprint($s);$copy=$s;$copy['captured_at']='later';
verifyClinical(AssessmentEvidenceService::fingerprint($copy)===$firstHash,'Capture timestamp must not cause a false stale-preview rejection');
$copy['sources'][0]['value']='Changed';verifyClinical(AssessmentEvidenceService::fingerprint($copy)!==$firstHash,'Changed assessment text must invalidate the preview');
$fields['allergies']['value']='Unknown';$s=AssessmentEvidenceService::snapshot($fields,['form'=>'LBF_TEST']);
verifyClinical(isset($s['missing']['allergies']),'Unknown allergy information must be flagged');
denyClinical(fn()=>AssessmentEvidenceService::assertReady($s,false));AssessmentEvidenceService::assertReady($s,true);
$fields['reason_visit']['value']='Not assessed';$s=AssessmentEvidenceService::snapshot($fields,[]);
denyClinical(fn()=>AssessmentEvidenceService::assertReady($s,true));
verifyClinical(strpos(AssessmentEvidenceService::promptSources($s),'[S1]')!==false,'Prompt must retain source references');
$profile=AssessmentProfileService::validate('LBF_ALTERNATE',['reason_visit'=>'chief_complaint']);
verifyClinical($profile['map']['reason_visit']==='chief_complaint','Alternate field aliases must be preserved');
denyClinical(fn()=>AssessmentProfileService::validate("LBF_TEST' OR 1=1",[]));
denyClinical(fn()=>AssessmentProfileService::validate('LBF_TEST',['unknown_identifier'=>'ssn']));
denyClinical(fn()=>AssessmentProfileService::validate('LBF_TEST',['reason_visit'=>'same','treatment_goals'=>'same']));
denyClinical(fn()=>ReviewChecklistService::validate(['review_sources'=>'1']));
verifyClinical(ReviewChecklistService::validate(['review_sources'=>'1','review_suggestions'=>'1','review_clinical'=>'1','review_note'=>'Checked'])==='Checked','Complete review must preserve note');
denyClinical(fn()=>ReviewChecklistService::validate(['review_sources'=>true,'review_suggestions'=>'1','review_clinical'=>'1']));
$input=['objective'=>'Sleep goal','status'=>'improving','measure'=>'Diary','note'=>'Synthetic','request_key'=>str_repeat('a',64)];
verifyClinical(ProgressService::validate($input)===$input,'Follow-up data must preserve its objective and measure');
denyClinical(fn()=>ProgressService::validate(array_replace($input,['status'=>'approved'])));
denyClinical(fn()=>ProgressService::validate(array_replace($input,['note'=>str_repeat('x',4001)])));
denyClinical(fn()=>ProgressService::validate(array_replace($input,['request_key'=>'invalid'])));
$review=\OpenEMR\Modules\TreatmentPlanner\Services\SourceReviewService::warnings('Suggested code 313.22 and [S999]',['sources'=>[['reference'=>'S1','value'=>'No confirmed diagnosis']]]);
verifyClinical(strpos(implode(' ',$review),'313.22')!==false && strpos(implode(' ',$review),'[S999]')!==false,'Unknown citations and unmatched code-like values must be flagged');
$review=\OpenEMR\Modules\TreatmentPlanner\Services\SourceReviewService::warnings('Clinical statement without references',['sources'=>[['reference'=>'S1','value'=>'Concern']]]);
verifyClinical(strpos($review[0],'No source references')!==false,'Missing source citations must remain visible');
echo "$count clinical support checks passed\n";
}
