const {execFileSync}=require('node:child_process');
module.exports=root=>{
 const php=code=>execFileSync('php',['-r',code],{cwd:root,encoding:'utf8'}).trim();
 const uid=1000000000+Math.floor(Math.random()*900000000);
 const sid=php(`chdir('public');require '../app/init.php';$d=new Database();$d->query('SELECT id FROM accounts WHERE id=${uid}');if($d->single())exit(1);session_id(bin2hex(random_bytes(24)));session_start();$_SESSION['auth_user']=['id'=>${uid},'name'=>'Panel test','email'=>'fixture@example.invalid'];echo session_id();session_write_close();`);
 return {sid,uid,cleanup(){php(`chdir('public');require '../app/init.php';$d=new Database();$d->query("SHOW TABLES LIKE 'account_workspace'");if($d->single()){$d->query('DELETE FROM account_workspace WHERE user_id=${uid}');$d->exe();}session_id('${sid}');session_start();$_SESSION=[];session_destroy();`);}};
};
