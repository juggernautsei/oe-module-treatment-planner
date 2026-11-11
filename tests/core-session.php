<?php
/** Optional interoperability smoke test against an installed core, without a database or network calls. */
$autoload = getenv('TP_CORE_AUTOLOAD');
if (!$autoload || !is_file($autoload)) { fwrite(STDERR,"Set TP_CORE_AUTOLOAD to the core vendor/autoload.php\n"); exit(2); }
require $autoload;
require dirname(__DIR__).'/src/Services/SessionContext.php';
require dirname(__DIR__).'/src/Services/CsrfService.php';
use OpenEMR\Modules\TreatmentPlanner\Services\{SessionContext,CsrfService};
$values=['authUser'=>'synthetic','authUserID'=>7,'pid'=>10,'encounter'=>20,'csrf_private_key'=>'synthetic-test-key'];
$modern=class_exists(\OpenEMR\Common\Session\SessionWrapperFactory::class);
if($modern){
 $session=new \Symfony\Component\HttpFoundation\Session\Session(new \Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage());
 foreach($values as $key=>$value)$session->set($key,$value);
 \OpenEMR\Common\Session\SessionWrapperFactory::getInstance()->setActiveSession($session);
 $_SESSION=[];
}else{$_SESSION=$values;}
if(!SessionContext::isAuthenticated() || SessionContext::get('pid')!==10 || SessionContext::get('encounter')!==20)throw new RuntimeException('Core session interoperability failed');
$token=CsrfService::collectCsrfToken('test-subject');
if(!CsrfService::verifyCsrfToken($token,'test-subject') || CsrfService::verifyCsrfToken($token,'wrong-subject') || CsrfService::verifyCsrfToken(['bad'],'test-subject'))throw new RuntimeException('Core CSRF interoperability failed');
echo ($modern?'Wrapper':'Native')." core session/CSRF interoperability passed on PHP ".PHP_VERSION."\n";
