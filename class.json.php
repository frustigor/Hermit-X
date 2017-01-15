<?php
class HermitJson
{
    private $token;
    private $_settings;

    public function __construct()
    {
        /**
         ** 缓存插件设置
         */
        $this->_settings = get_option('hermit_setting');
        require('include/Meting.php');
    }

    public function song_url($site, $music_id)
    {
        $Meting = new Meting($site);

        $url = json_decode($Meting->format()->url($music_id, $this->settings('quality')), true);
        $url = $url['url'];
        if (empty($url)) {
            if ($this->settings('within_China')) {
                Header("Location: " . 'https://api.lwl12.com/music/netease/song?id=607441');
            } else {
                Header("Location: " . "https://api.lwl12.com/music/$site/song?id=" . $music_id);
            }

            exit;
        }
        Header("Location: " . $url);
        exit;
    }

    public function pic_url($site, $id, $pic)
    {
        $Meting = new Meting($site);

        $pic = json_decode($Meting->pic($pic), true);
        Header("Location: " . $pic["url"]);
        exit;
    }

    public function id_parse($site, $src)
    {
        foreach ($src as $key => $value) {
            $cacheKey = "/$site/idparse/$value";
            $cache    = $this->get_cache($cacheKey);
            if ($cache) {
                $ids[] = $cache;
                continue;
            }
            $response = wp_remote_retrieve_body(wp_remote_get($value));
            switch ($site) {
                case 'xiami':
                    $re       = '/<link rel="canonical" href="http:\/\/www\.xiami\.com\/(collect|album|song)\/(?<id>\d+)" \/>/';
                    break;
                case 'tencent':
                    $re = '/g_SongData.*"songmid":"(?<id>[A-Za-z0-9]+)".*"songtype"/';
                    break;
                default:
                    return false;
                    break;
            }

            preg_match($re, $response, $matches);
            $ids[] = $matches['id'];
            $this->set_cache($cacheKey, $matches['id'], 744);
        }
        return $ids;
    }

    public function song($site, $music_id)
    {
        $Meting = new Meting($site);
        $cache_key = "/$site/song/$music_id";

        $cache = $this->get_cache($cache_key);
        if ($cache) {
            return $cache;
        }

        $response = json_decode($Meting->format()->song($music_id), true);

        if (!empty($response[0]["id"])) {
            //处理音乐信息
            $mp3_url    = admin_url() . "admin-ajax.php" . "?action=hermit&scope=" . $site . "_song_url&id=" . $music_id;
            $music_name = $response[0]['name'];
            $cover      = admin_url() . "admin-ajax.php" . "?action=hermit&scope=" . $site . "_pic_url&picid=" . $response[0]['pic_id'] . '&id=' . $music_id;
            $artists    = $response[0]['artist'];

            $artists = implode(",", $artists);

            $result = array(
                "title" => $music_name,
                "author" => $artists,
                "url" => $mp3_url,
                "pic" => $cover,
                "lrc" => "https://api.lwl12.com/music/$site/lyric?raw=true&id=" . $music_id
            );

            $this->set_cache($cache_key, $result, 24);

            return $result;
        }

        return false;
    }

    public function songlist($site, $song_list)
    {
        if (!$song_list) {
            return false;
        }

        $songs_array = explode(",", $song_list);
        $songs_array = array_unique($songs_array);

        if (!empty($songs_array)) {
            $result = array();
            foreach ($songs_array as $song_id) {
                $result['songs'][] = $this->song($site, $song_id);
            }
            return $result;
        }

        return false;
    }

    public function album($site, $album_id)
    {
        $Meting = new Meting($site);
        $cache_key = "/$site/album/$album_id";

        $cache = $this->get_cache($cache_key);
        if ($cache) {
            return $cache;
        }

        $response = json_decode($Meting->format()->album($album_id), true);

        if (!empty($response[0])) {
            //处理音乐信息
            $result = $response;
            $count  = count($result);

            if ($count < 1) {
                return false;
            }

            $album = array(
                "album_id" => $album_id,
                "album_type" => "albums",
                "album_count" => $count
            );


            foreach ($result as $k => $value) {
                $mp3_url          = admin_url() . "admin-ajax.php" . "?action=hermit&scope=" . $site . "_song_url&id=" . $value["id"];
                $album["songs"][] = array(
                    "title" => $value["name"],
                    "url" => $mp3_url,
                    "author" => $album_author = implode(",", $value['artist']),
                    "pic" => admin_url() . "admin-ajax.php" . "?action=hermit&scope=" . $site . "_pic_url&picid=" . $value['pic_id'] . '&id=' . $value['id'],
                    "lrc" => "https://api.lwl12.com/music/$site/lyric?raw=true&id=" . $value["id"]
                );
            }

            $this->set_cache($key, $album, 24);
            return $album;
        }

        return false;
    }

    public function playlist($site, $playlist_id)
    {
        $Meting = new Meting($site);
        $cache_key = "/$site/playlist/$playlist_id";

        $cache = $this->get_cache($cache_key);
        if ($cache) {
            return $cache;
        }

        $response = json_decode($Meting->format()->playlist($playlist_id), true);

        if (!empty($response[0])) {
            //处理音乐信息
            $result = $response;
            $count  = count($result);

            if ($count < 1) {
                return false;
            }

            $playlist = array(
                "playlist_id" => $playlist_id,
                "playlist_type" => "playlists",
                "playlist_count" => $count
            );

            foreach ($result as $k => $value) {
                $mp3_url = admin_url() . "admin-ajax.php" . "?action=hermit&scope=" . $site . "_song_url&id=" . $value["id"];
                $artists = $value["artist"];

                $artists = implode(",", $artists);

                $playlist["songs"][] = array(
                    "title" => $value["name"],
                    "url" => $mp3_url,
                    "author" => $artists,
                    "pic" => admin_url() . "admin-ajax.php" . "?action=hermit&scope=" . $site . "_pic_url&picid=" . $value['pic_id'] . '&id=' . $value['id'],
                    "lrc" => "https://api.lwl12.com/music/$site/lyric?raw=true&id=" . $value["id"]
                );
            }

            $this->set_cache($cache_key, $playlist, 24);
            return $playlist;
        }

        return false;
    }

    public function get_cache($key)
    {
        if ($this->settings('advanced_cache')) {
            $cache = wp_cache_get($key, 'hermit');
        } else {
            $cache = get_transient($key);
        }

        return $cache === false ? false : json_decode($cache, true);
    }

    public function set_cache($key, $value, $hour = 0.1)
    {
        $value = json_encode($value);

        if ($this->settings('advanced_cache')) {
            wp_cache_set($key, $value, 'hermit', 60 * 60 * $hour);
        } else {
            set_transient($key, $value, 60 * 60 * $hour);
        }
    }

    public function clear_cache($key)
    {
        //delete_transient($key);
    }

    /**
     * settings - 插件设置
     *
     * @param $key
     *
     * @return bool
     */
    public function settings($key)
    {
        $defaults = array(
            'tips' => '点击播放或暂停',
            'strategy' => 1,
            'color' => 'default',
            'playlist_max_height' => '349',
            'quality' => '320',
            'jsplace' => 0,
            'prePage' => 20,
            'remainTime' => 10,
            'roles' => array(
                'administrator'
            ),
            'albumSource' => 0,
            'debug' => 0,
            'advanced_cache' => 0,
            'within_China' => 1,
        );

        $settings = $this->_settings;
        $settings = wp_parse_args($settings, $defaults);

        return $settings[$key];
    }
}
