<?php namespace App\Http\Controllers;
use App\Support\ResourceRules;use App\Models\Admin;use App\Support\Jwt;use Illuminate\Http\Request;use Illuminate\Support\Facades\{DB,Hash};use Illuminate\Support\Str;
class ApiController{
private function ok($d=null,$m='Success',$c=200){$p=['status'=>1,'message'=>$m];if($d!==null)$p['data']=$d;return response()->json($p,$c);}private function no($m,$c=400){return response()->json(['status'=>0,'message'=>$m],$c);}
private function log(Request $r,string $action,string $entity):void{$a=$r->attributes->get('admin');DB::table('activity_logs')->insert(['admin_id'=>$a?->id,'admin_name'=>$a?->name,'action'=>$action,'entity'=>$entity,'created_at'=>now()]);}
private array $map=['admins'=>['admins','name'],'categories'=>['course_categories','name'],'courses'=>['courses','title'],'branches'=>['branches','name'],'testimonials'=>['testimonials','name'],'features'=>['features','title'],'enquiries'=>['enquiries','name'],'blog'=>['blog_posts','title'],'gallery'=>['gallery_images','image'],'awards'=>['awards','title']];
function health(){return ['status'=>1,'message'=>'Simatrix Academy API running'];}
function login(Request $r){$d=$r->validate(['email'=>'required|email|max:120','password'=>'required|string|max:128']);$a=Admin::where('email',strtolower(trim($d['email'])))->first();if(!$a||!Hash::check($d['password'],$a->password_hash))return $this->no('Invalid email or password',401);return $this->ok(['token'=>Jwt::issue($a->id),'admin'=>$a],'Login successful');}function me(Request $r){return $this->ok($r->attributes->get('admin'));}
function site(){return $this->ok(['features'=>DB::table('features')->orderBy('order')->get(),'categories'=>$this->cats(),'branches'=>$this->branchesData(),'testimonials'=>DB::table('testimonials')->where('is_active',1)->orderBy('order')->get(),'projects'=>DB::table('gallery_images')->where('category','student-project')->orderBy('order')->limit(6)->get(),'settings'=>$this->settingsData(),'hero_slides'=>json_decode($this->settingsData()['hero_slides']??'[]',true)?:[]]);}
private function cats(){return DB::table('course_categories')->orderBy('order')->get()->map(function($c){$c->courses=DB::table('courses')->where('category_id',$c->id)->where('is_active',1)->orderBy('order')->get()->map(fn($x)=>$this->jsonCourse($x));return $c;});}function categories(){return $this->ok($this->cats());}
private function jsonCourse($x){foreach(['syllabus','roadmap','designations','prerequisites','projects','quiz'] as $f)$x->$f=json_decode($x->$f??'[]',true)?:[];if(isset($x->category_slug)){$x->category=['slug'=>$x->category_slug,'name'=>$x->category_name];unset($x->category_slug,$x->category_name);}return $x;}
function courses(Request $r){
$d=$r->validate(['category'=>'nullable|string|max:140','q'=>'nullable|string|max:120','level'=>'nullable|string|max:60','duration'=>'nullable|string|max:60','sort'=>'nullable|in:recommended,title,duration']);
$q=DB::table('courses as c')->join('course_categories as cc','cc.id','=','c.category_id')->where('c.is_active',1)->select('c.*','cc.slug as category_slug','cc.name as category_name');
if(!empty($d['category']))$q->where('cc.slug',$d['category']);
if(!empty($d['q'])){$term='%'.addcslashes($d['q'],'%_\\').'%';$q->where(fn($query)=>$query->where('c.title','like',$term)->orWhere('c.summary','like',$term)->orWhere('c.description','like',$term));}
if(!empty($d['level']))$q->where('c.level',$d['level']);if(!empty($d['duration']))$q->where('c.duration',$d['duration']);
if(($d['sort']??'recommended')==='title')$q->orderBy('c.title');elseif(($d['sort']??'recommended')==='duration')$q->orderBy('c.duration');else $q->orderBy('c.order')->orderBy('c.title');
return $this->ok($q->get()->map(fn($x)=>$this->jsonCourse($x)));
}
function course($slug){$x=DB::table('courses as c')->join('course_categories as cc','cc.id','=','c.category_id')->where('c.slug',$slug)->where('c.is_active',1)->select('c.*','cc.slug as category_slug','cc.name as category_name')->first();return $x?$this->ok($this->jsonCourse($x)):$this->no('Course not found',404);}
private function branchesData(){return DB::table('branches')->orderByDesc('is_primary')->orderBy('order')->get()->map(function($b){$b->map_src=$b->map_embed?:'https://maps.google.com/maps?q='.rawurlencode($b->address?:"$b->name, $b->city, Tamil Nadu, India").'&z=15&output=embed';return $b;});}function branches(){return $this->ok($this->branchesData());}
function listPublic($r){[$t]=$this->map[$r];$q=DB::table($t);if($r==='blog')$q->where('is_published',1)->orderByDesc('created_at');elseif($r==='testimonials')$q->where('is_active',1)->orderBy('order');else $q->orderBy('order');return $this->ok($q->get());}function detailBlog($s){$x=DB::table('blog_posts')->where(['slug'=>$s,'is_published'=>1])->first();return $x?$this->ok($x):$this->no('Article not found',404);}
function review(Request $r){$d=$r->validate(['name'=>'required|string|max:120','role'=>'nullable|string|max:160','content'=>'required|string|min:10|max:5000','rating'=>'sometimes|integer|between:1,5']);$id=DB::table('testimonials')->insertGetId($d+['is_active'=>0,'moderation_status'=>'pending','order'=>0]);return $this->ok(['id'=>$id],'Thank you! Your review will appear after approval.',201);}
function enquiry(Request $r){
$r->validate(['program'=>'nullable|in:internship,career-guidance']);
$type=['internship'=>'govt_intern','career-guidance'=>'career_guidance'][$r->program]??($r->type?:'contact');
$r->merge(['type'=>$type]);$rules=ResourceRules::for('enquiries');
foreach(['status','assigned_to','follow_up_at'] as $f)unset($rules[$f]);$rules['consent']='required|accepted';
if(in_array($type,['govt_intern','career_guidance']))foreach(['email','college','degree'] as $f)$rules[$f]=str_replace('nullable','required',$rules[$f]);
$d=$r->validate($rules);$id=DB::table('enquiries')->insertGetId($d+['status'=>'new','created_at'=>now()]);$reference='CB-'.now()->format('Ym').'-'.str_pad((string)$id,6,'0',STR_PAD_LEFT);DB::table('enquiries')->where('id',$id)->update(['reference'=>$reference]);return $this->ok(['id'=>$id,'reference'=>$reference],'Thank you! Our team will reach out to you shortly.',201);}
function adminList(Request $request,$r){
if($r==='admins')$q=Admin::query()->select(['id','name','email','role','created_at']);elseif($r==='activity')$q=DB::table('activity_logs')->orderByDesc('created_at');elseif(!isset($this->map[$r]))return $this->no('Resource not found',404);elseif($r==='enquiries')$q=DB::table('enquiries as e')->leftJoin('courses as c','c.id','=','e.course_id')->leftJoin('admins as a','a.id','=','e.assigned_to')->select('e.*','c.title as course_title','a.name as assigned_name');else $q=DB::table($this->map[$r][0]);
$filters=$request->validate(['q'=>'nullable|string|max:120','status'=>'nullable|string|max:40','page'=>'nullable|integer|min:1','per_page'=>'nullable|integer|between:1,100']);
if(!empty($filters['q'])){$column=$r==='admins'?'name':($r==='enquiries'?'e.name':$this->map[$r][1]??'action');$q->where($column,'like','%'.addcslashes($filters['q'],'%_\\').'%');}
if($r==='enquiries'&&!empty($filters['status']))$q->where('e.status',$filters['status']);
$order=match($r){'enquiries'=>'e.created_at','admins','blog','activity'=>'created_at',default=>'order'};$q->orderBy($order,'desc');
if(isset($filters['page'])||isset($filters['per_page'])){$page=$q->paginate($filters['per_page']??25);$items=collect($page->items())->map(fn($x)=>$r==='courses'?$this->jsonCourse($x):$x);return $this->ok(['items'=>$items,'pagination'=>['current_page'=>$page->currentPage(),'last_page'=>$page->lastPage(),'per_page'=>$page->perPage(),'total'=>$page->total()]]);}
return $this->ok($q->limit($r==='activity'?100:1000)->get()->map(fn($x)=>$r==='courses'?$this->jsonCourse($x):$x));}
private function resourceData(Request $q,string $r,?int $id=null):array{
if($id===null&&in_array($r,['categories','courses','blog'])&&!$q->filled('slug')&&is_string($q->input($this->map[$r][1])))$q->merge(['slug'=>Str::slug((string)$q->input($this->map[$r][1],''))]);
$rules=ResourceRules::for($r,$id);
if($r==='enquiries'&&$q->attributes->get('admin')->role==='staff')$rules=array_intersect_key($rules,array_flip(['status','assigned_to','follow_up_at']));
$d=$q->validate($rules);
if(isset($d['email']))$d['email']=strtolower(trim($d['email']));
if(isset($d['password'])){$d['password_hash']=Hash::make($d['password']);unset($d['password']);}
foreach(['syllabus','roadmap','designations','prerequisites','projects','quiz'] as $f)if(array_key_exists($f,$d))$d[$f]=json_encode($d[$f]??[]);
return $d;
}
private function record(string $r,int $id){$x=DB::table($this->map[$r][0])->find($id);if($r==='admins')unset($x->password_hash);return $r==='courses'?$this->jsonCourse($x):$x;}
function create(Request $q,$r){
if(!isset($this->map[$r]))return $this->no('Resource not found',404);
$d=$this->resourceData($q,$r);
if($r==='testimonials'&&isset($d['moderation_status']))$d['is_active']=$d['moderation_status']==='approved';
if(in_array($r,['admins','blog','gallery','enquiries']))$d['created_at']=now();
$id=DB::table($this->map[$r][0])->insertGetId($d);$this->log($q,'created',$r.':'.$id);return $this->ok($this->record($r,$id),'Created successfully',201);
}
function update(Request $q,$r,$id){
if(!isset($this->map[$r]))return $this->no('Resource not found',404);
$t=$this->map[$r][0];if(!DB::table($t)->find($id))return $this->no('Resource not found',404);
$d=$this->resourceData($q,$r,(int)$id);
if($r==='admins'&&(int)$id===$q->attributes->get('admin')->id&&isset($d['role'])&&$d['role']!=='admin')return $this->no('You cannot demote your own account',422);
if($r==='testimonials'&&(array_key_exists('is_active',$d)||array_key_exists('moderation_status',$d))){$d['moderation_status']=$d['moderation_status']??($d['is_active']?'approved':'rejected');$d['is_active']=$d['moderation_status']==='approved';$d['moderated_by']=$q->attributes->get('admin')->id;$d['moderated_at']=now();}
if($d)DB::table($t)->where('id',$id)->update($d);$this->log($q,'updated',$r.':'.$id);return $this->ok($this->record($r,(int)$id));
}
function delete(Request $q,$r,$id){
if(!isset($this->map[$r]))return $this->no('Resource not found',404);
if($r==='admins'&&(int)$id===$q->attributes->get('admin')->id)return $this->no('You cannot remove your own account',422);
if(!DB::table($this->map[$r][0])->where('id',$id)->delete())return $this->no('Resource not found',404);$this->log($q,'deleted',$r.':'.$id);
return $this->ok(null,'Deleted successfully');
}
private function settingsData(){return array_merge(['contact_phone'=>'096777 81155','contact_phone2'=>'096777 24437','contact_email'=>'info@elysiumacademy.org','contact_address'=>'227, IInd Floor, Church Road, Anna Nagar, Madurai - 625020','whatsapp'=>'919677781155','hero_slides'=>'[]'],DB::table('settings')->pluck('value','key')->all());}function settings(){return $this->ok($this->settingsData());}function saveSettings(Request $r){
$rules=['contact_phone'=>'nullable|string|max:120','contact_phone2'=>'nullable|string|max:120','contact_email'=>'nullable|email|max:120','contact_address'=>'nullable|string|max:2000','whatsapp'=>'nullable|string|max:40','hero_title'=>'nullable|string|max:200','hero_subtitle'=>'nullable|string|max:2000','hero_slides'=>'nullable|json|max:20000'];
foreach(['facebook','instagram','youtube','linkedin'] as $social)$rules['social_'.$social]='nullable|url:http,https|max:2000';
$d=$r->validate($rules);DB::transaction(function()use($d){foreach($d as $k=>$v)DB::table('settings')->updateOrInsert(['key'=>$k],['value'=>$v]);});$this->log($r,'updated','settings');return $this->ok($this->settingsData(),'Settings saved');}
function upload(Request $r){if(!$r->hasFile('file'))return $this->no('No file provided');$r->validate(['file'=>'required|file|image|max:5120|mimes:png,jpg,jpeg,gif,webp|dimensions:max_width=6000,max_height=6000']);$f=$r->file('file');$n=Str::random(40).'.'.$f->extension();$f->move(public_path('src/assets'),$n);$this->log($r,'uploaded','image');return $this->ok(['url'=>"/src/assets/$n",'filename'=>$n],'Created successfully',201);}
function notes($id){abort_unless(DB::table('enquiries')->where('id',$id)->exists(),404);return $this->ok(DB::table('enquiry_notes')->where('enquiry_id',$id)->orderByDesc('created_at')->get());}function addNote(Request $r,$id){$r->validate(['body'=>'required|string|max:5000']);abort_unless(DB::table('enquiries')->where('id',$id)->exists(),404);$a=$r->attributes->get('admin');$nid=DB::table('enquiry_notes')->insertGetId(['enquiry_id'=>$id,'admin_id'=>$a->id,'admin_name'=>$a->name,'body'=>trim($r->body),'created_at'=>now()]);$this->log($r,'noted','enquiries:'.$id);return $this->ok(DB::table('enquiry_notes')->find($nid),'Created successfully',201);}
}
