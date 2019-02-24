#!/usr/bin/php
<?php

if (empty($argv[1])) die("Wrong parameters");

if ($argv[1] === '-u') {
    $query = parse_url($argv[2], PHP_URL_QUERY);
    parse_str($query, $qw);

    if (empty($qw['video_id']) && empty($qw['v'])) die("Wrong URL");
    
    if (!empty($qw['video_id'])) $videoIds = [$qw['video_id']];
    if (!empty($qw['v'])) $videoIds = [$qw['v']];
    
} elseif($argv[1] === '-l') {
    $urls = file('./.videos', FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
    $videoIds = [];
    foreach ($urls as $url) {
		$query = parse_url($url, PHP_URL_QUERY);
		parse_str($query, $qw);

		if (empty($qw['video_id'])) die("Wrong URL");
		
		$videoIds[] = $qw['video_id'];
    }
}

foreach ($videoIds as $videoId) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, "https://www.youtube.com/get_video_info?video_id={$videoId}");
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_COOKIE, file_get_contents('./.cookie'));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

	$videoInfo = curl_exec($ch);
	parse_str($videoInfo, $output);
	curl_close($ch);

    $is_1080p = false;
    $is_720p = false;
    $is_480p = false;
	if (array_key_exists('adaptive_fmts', $output) && array_key_exists('title', $output)) {
		if (!file_exists("videos/{$output['title']}")) {
			mkdir("videos/{$output['title']}", 0755, true);
		}
		$files = explode(',', $output['url_encoded_fmt_stream_map']);
		foreach ($files as $file) {
			parse_str($file, $arr);
			var_export($arr);
			list($mime, $other) = explode(';', $arr['type']);
			list($type, $ext) = explode('/', $mime);
			if ($type !== 'video' || $ext !== 'mp4') {
				continue;
			}
			if ($arr['quality'] === 'hd720') {
			    $is_720p = true;
			} elseif ($arr['quality'] === 'medium') {
			    $is_480p = true;
			} else {
				continue;
			}

			if ($type === 'video') {
				$filename = "videos/{$output['title']}/{$type}_{$arr['itag']}_{$arr['quality']}.{$ext}";
			} elseif ($type === 'audio') {
				$filename = "videos/{$output['title']}/{$type}_{$arr['itag']}_{$arr['audio_sample_rate']}.{$ext}";
			}

            if (!file_exists($filename)) {
				touch($filename);
				echo "Created {$filename}\n";

				$fp = fopen($filename, 'w');
				if (!$fp) {
					die("Cannot open file");
				}

				echo "Getting {$arr['url']}\n";
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $arr['url']);
				curl_setopt($ch, CURLOPT_FILE, $fp);
				curl_setopt($ch, CURLOPT_HEADER, 0);
				curl_setopt($ch, CURLOPT_COOKIE, file_get_contents('./.cookie'));
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
				if (curl_exec($ch) === false) {
					echo "Ошибка curl: ", curl_error($ch), "\n\n";
					curl_close($ch);
					fclose($fp);
					unlink($filename);
				} else {
					$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
					echo "Done {$httpCode}\n\n";
					curl_close($ch);
					fclose($fp);
					if ($httpCode != 200) {
						unlink($filename);
					}
				}
			}
			if ($is_720p) break;
			if ($is_480p) break;
		}
		$files = explode(',', $output['adaptive_fmts']);
		foreach ($files as $file) {
			parse_str($file, $arr);
			list($mime, $other) = explode(';', $arr['type']);
			list($type, $ext) = explode('/', $mime);
			if ($type !== 'video' || $ext !== 'mp4') {
				continue;
			}
			if ($arr['quality_label'] === '1080p') {
			    $is_1080p = true;
			} elseif ($arr['quality_label'] === '720p' && !$is_720p) {
			    $is_720p = true;
			} elseif ($arr['quality_label'] === '480p' && !$is_480p && !$is_720p) {
			    $is_480p = true;
			} else {
				continue;
			}

			if ($type === 'video') {
				$filename = "videos/{$output['title']}/{$type}_{$arr['itag']}_{$arr['quality_label']}.{$ext}";
			} elseif ($type === 'audio') {
				$filename = "videos/{$output['title']}/{$type}_{$arr['itag']}_{$arr['audio_sample_rate']}.{$ext}";
			}

            if (!file_exists($filename)) {
				touch($filename);
				echo "Created {$filename}\n";

				$fp = fopen($filename, 'w');
				if (!$fp) {
					die("Cannot open file");
				}

				echo "Getting {$arr['url']}\n";
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $arr['url']);
				curl_setopt($ch, CURLOPT_FILE, $fp);
				curl_setopt($ch, CURLOPT_HEADER, 0);
				curl_setopt($ch, CURLOPT_COOKIE, file_get_contents('./.cookie'));
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
				if (curl_exec($ch) === false) {
					echo "Ошибка curl: ", curl_error($ch), "\n\n";
					curl_close($ch);
					fclose($fp);
					unlink($filename);
				} else {
					$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
					echo "Done {$httpCode}\n\n";
					curl_close($ch);
					fclose($fp);
					if ($httpCode != 200) {
						unlink($filename);
					}
				}
            }

			if ($is_1080p) break;
			if ($is_720p) break;
			if ($is_480p) break;
		}
	}
	else {
		var_export($output);
	}
}