<?php
namespace App\Http\Controllers;
use App\Models\ForumTopic;
use App\Models\ForumReply;
use Illuminate\Http\Request;
class ForumController extends Controller {
 private const CATEGORIES=['pet-bakimi'=>'Pet bakımı','saglik'=>'Sağlık ve veteriner','egitim'=>'Eğitim ve davranış','beslenme'=>'Beslenme','pet-otelleri'=>'Pet otelleri','genel'=>'Genel sohbet'];
 public function index(Request $r){
   $q=ForumTopic::where('status','active')
     ->where(function($q2){ $q2->whereNull('publish_at')->orWhere('publish_at','<=',now()); })
     ->with('user:id,name,username')
     ->withCount(['replies'=>fn($x)=>$x->where('status','active')])
     ->latest();
   if($r->filled('category')&&isset(self::CATEGORIES[$r->category]))$q->where('category',$r->category);
   if($r->filled('q'))$q->where('title','like','%'.mb_substr($r->q,0,100).'%');
   return view('forum.index',['topics'=>$q->paginate(20)->withQueryString(),'categories'=>self::CATEGORIES]);
 }
 public function rss(){
   $topics=ForumTopic::query()
     ->where('status','active')
     ->where(function($q){ $q->whereNull('publish_at')->orWhere('publish_at','<=',now()); })
     ->latest()
     ->limit(50)
     ->get(['title','slug','body','created_at']);
   return response()->view('forum.rss',compact('topics'),200,[
     'Content-Type'=>'application/rss+xml; charset=UTF-8',
   ]);
 }
 public function show(string $slug){
   $topic=ForumTopic::where('slug',$slug)->first();
   if(!$topic){
     if(\Illuminate\Support\Facades\Schema::hasTable('forum_slug_redirects')){
       $hit=\Illuminate\Support\Facades\DB::table('forum_slug_redirects')->where('old_slug',$slug)->first();
       if($hit){
         $new=ForumTopic::where('id',$hit->forum_topic_id)->value('slug');
         if($new&&$new!==$slug)return redirect('/forum/'.$new,301);
       }
     }
     abort(404);
   }
   $isPublished=$topic->status==='active'&&(!$topic->publish_at||$topic->publish_at->lte(now()));
   $isOwner=auth()->id()!==null&&(int)auth()->id()===(int)$topic->user_id;
   abort_unless($isPublished||$isOwner||auth()->user()?->isAdmin(),404);
   $topic->increment('views');
   $topic->load('user:id,name,username');
   $replies=$topic->replies()->where('status','active')->with('user:id,name,username')->oldest()->paginate(30);
   return view('forum.show',compact('topic','replies'));
 }
 public function create(){return view('forum.create',['categories'=>self::CATEGORIES]);}
 public function store(Request $r){
   if(!\App\Support\Captcha::check($r,'forum-topic'))return back()->withErrors(['captcha'=>'Güvenlik kontrolü başarısız.'])->withInput();
   $d=$r->validate(['category'=>'required|in:'.implode(',',array_keys(self::CATEGORIES)),'title'=>'required|string|min:8|max:180','body'=>'required|string|min:30|max:10000']);
   $d['user_id']=$r->user()->id;$d['status']='pending';ForumTopic::create($d);
   return redirect()->route('forum.index')->with('ok','Konunuz moderasyon kuyruğuna alındı. Onaylanınca forumda görünecek.');
 }
 public function reply(Request $r,ForumTopic $topic){
   abort_unless($topic->status==='active',404);
   if(!\App\Support\Captcha::check($r,'forum-reply'))return back()->withErrors(['captcha'=>'Güvenlik kontrolü başarısız.'])->withInput();
   $d=$r->validate(['body'=>'required|string|min:5|max:5000']);
   $topic->replies()->create(['user_id'=>$r->user()->id,'body'=>$d['body'],'status'=>'pending']);
   return back()->with('ok','Yanıtınız incelenmek üzere gönderildi.');
 }
 public function queue(){
   return view('forum.queue',['topics'=>ForumTopic::where('status','pending')->with('user:id,name,username')->latest()->paginate(20),'replies'=>ForumReply::where('status','pending')->with(['user:id,name,username','topic:id,title'])->latest()->paginate(20)]);
 }
 public function moderateTopic(Request $r,ForumTopic $topic){
   $d=$r->validate(['status'=>'required|in:active,rejected']);$topic->update(['status'=>$d['status']]);
   return back()->with('ok','Konu durumu güncellendi.');
 }
 public function moderateReply(Request $r,ForumReply $reply){
   $d=$r->validate(['status'=>'required|in:active,rejected']);$reply->update(['status'=>$d['status']]);
   return back()->with('ok','Yanıt durumu güncellendi.');
 }
}
