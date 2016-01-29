<! ---学生証番号：344270　氏名：岡山愛
　　　作成最終日（更新日）：2016.1.25　進行状況：　　---->
<html>
    <head>
        <title>report_final</title>
        <meta http-equiv="content-type" content="text/html;charset=utf-8">
    </head>

<body>
<?php
    
    /*urlから情報を取得する関数*/
    function httpRequest($url){
      

        $purl = parse_url($url);//urlを解析して連想配列に格納
        //print_r($purl); //添え字=>要素 を表す形式で値を保存
        $psheme = $purl["scheme"];//スキーマを取得
        $phost = $purl["host"];//ホスト名を取得
            
        //ポート番号が空の場合、デフォルトを設定
        if(!isset($purl["port"])){
            $pport = 80;
        }else{
            $pport = $purl["port"];
        }
        //パスが空の場合、デフォルトを設定
        if(!isset($purl["path"])){
            $ppath = "/";
        }else{
            $ppath = $purl["path"];
        }
       
        //echo "スキーマは「".$psheme."」、ホスト名は「".$phost."」、ポートは「".$pport."」、パスは「".$ppath."」です。";
     
        
        
        $fp = fsockopen($phost,$pport,$errno,$errstr);//サーバ接続
        socket_set_timeout($fp,10);//ソケットのタイムアウトを設定
        
        $request = "GET ".$ppath." HTTP/1.0\r\n\r\n";//リクエスト内容
        
        fputs($fp,$request);//ファイルに書き込む
        
        $response = "";
        
        while(!feof($fp)){//ファイルから１ぎょうずつ取得する
            $response .= fgets($fp, 4096);//4KB分のデータをとってくる
        }
        fclose ($fp);//ファイルポインタを閉じる
        $DATA = explode("\r\n\r\n",$response,2);
    
        return $DATA;
    }
    /*データベースに格納する関数*/
    function insert_sql($table,$url,$contents){
        
        try{
            $dbh = new PDO('sqlite:test.db','',''); //PDOクラスのオブジェクト作成
            //データベースに格納する
            $sql ='insert into '.$table.'(url,contents) values (?,?)';//SQL文
            $sth = $dbh->prepare($sql); //prepareメソッドでSQL準備
            $sth->execute(array($url,$contents)); //準備したSQL文の実行
            //データベースを検索し情報を抽出
            $q = "'%t%'";
            $sql = "select * from $table where contents like $q";
            $sth = $dbh->prepare($sql);
            $sth->execute();
            
            while($row = $sth->fetch()){
                //echo $row['url'].$row['contents']."<br>";
            }
            
        }Catch (PDOException $e) {
            print "エラー!: " . $e->getMessage() . "<br/>";
            die();
        }
    }
    
    
    /*文字列の形態素解析を行い、指定した品詞の単語情報を出力する関数*/
    function yahoo_mecab($data,$hinshi,$url){
        
        $apikey='dj0zaiZpPUpZVzNaZ3oxeExXMSZzPWNvbnN1bWVyc2VjcmV0Jng9Yjc-';//yahooAPIキー
        //$query = "冬の京都も美しい。";
        $query = urlencode($data);//urlの標準コード（utf8）に変換
        $res = "surface,reading,pos,feature";//レスポンスで返信される形態素情報
        
        $q_url = 'http://jlp.yahooapis.jp/MAService/V1/parse?appid='.$apikey.'&response='.$res.'&sentence='.$query; //レスポンスで返信される形態素情報を指定したurl：「?」以下は、リクエストパラメータ
        
        $rss = file_get_contents($q_url);//リクエスト送信&レスポンス取得
        $xml = simplexml_load_string($rss);//取得したXMLを解析
        $a = $hinshi;
        
        foreach($xml->ma_result->word_list->word as $item){
            if(ereg($a,$item->feature) == 1){//名詞なら、情報を出力
                $contents = $item->surface;
                insert_sql('list',$url,$contents);
                //echo $item->surface."|".$item->feature;
                //echo "<br>";
                $tango[]= "$contents";//単語を単語リストに格納
            }
            
        }
        return $tango;//単語リストを返す
    }
    
    ?>

<!------------------------------------------------------------------------------------------------>
<h3>実践Webアプリケーション最終課題</h3>
<!-- 入力フォーム -->
<form action = "<?php echo $_SERVER['PHP_SELF']?>" method="post"> <!-- 入力を受け取るのは自分自身 -->
<p>
<input type="text" name="query" /> <!--入力された文字にqueryという名前をつけて転送-->
<input type="submit" value="送信" /> <!--送信ボタンを表示-->※制限100KB<br>
<hr>
出現回数の多い単語トップ１０

</p>
</form>
<hr>
<!----------------------------------------------------------------------------------------------->
<?php
    
    $query = $_POST['query'];//￼￼￼￼￼￼￼￼￼￼￼￼￼￼転送されたデータ（全ての変数）に対して、'name'で指定した文字列を添字とした要素を変数へ代入
    
    if(isset($query)){//q_queryに値があったら表示
        
        
        list($head,$data)= httpRequest($query);//(2)取得したページの文章だけを取り出す
        $data = strip_tags($data);//タグ除去
        $hinshi = "名詞";
        //(3)1リクエストにつき100KB制限
        $start=0;
        $size=512; //100KB=1024*100/2=512文字だが、file_get_contents()制限の為
        $end = mb_strlen($data);
        do{
            $data2 = mb_substr($data,$start-$end,$size);
            $tango=yahoo_mecab($data2,$hinshi,$query);
            $start += $size;
            foreach($tango as $val){//word配列に単語を格納
                $word["$val"]=$val;
            }
            
        }while(($start-$end)<=$end);
        foreach($word as $v){//配列word内の重複を削除した配列を作成
            $keyword = array_search($v,$word);
            if($keyword === false){
                $list_word["$v"] = 1;
            }
        }
        try{
            $dbh = new PDO('sqlite:test.db','',''); //PDOクラスのオブジェクト作成
            //データベースを検索し情報を抽出
            //$q = "'%スポーツ%'";
            foreach($word as $keyword => $value){
                $q="'".$keyword."'";
                $sql = "select count(*) from list where contents like $q";
                $sth = $dbh->prepare($sql);
                $sth->execute();
                $cnt = $sth->fetchColumn();//
                //echo $cnt;
                $list_word["$keyword"]=$cnt;
            }
            $sql = "select count(*) from list";
            $sth = $dbh->prepare($sql);
            $sth->execute();
            $word_all_cnt = $sth->fetchColumn();//

            
        }Catch (PDOException $e) {
            print "エラー!: " . $e->getMessage() . "<br/>";
            die();
        }
        
        arsort($list_word);
        
        $i = 1;
        foreach($list_word as $k => $v){
            $tf= $v/$word_all_cnt;
            if($i <= 10){
                echo $i."位：".$k."(".$v."回  TF値=".round($tf,2).")<br>";
                $i++;
            }else{
                break;
            }
        }
  
        
        
        
        
        
    }else{
        echo "URLを入力してください。";
    }
    
    
    
    ?>



    </body>
</html>
