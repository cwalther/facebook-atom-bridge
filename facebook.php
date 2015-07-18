<?php

/*
Copyright (c) 2015 Christian Walther

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
*/

//ini_set('display_errors','On');
//error_reporting(E_ALL | E_STRICT);

$access_token = 'INSERT TOKEN HERE (app_id|app_secret)';

if (!isset($_GET['id']) || !isset($_GET['count'])) exit('param');

$page_id = urlencode($_GET['id']);
$count = urlencode($_GET['count']);

// test content
//$text = '{…}';

$etag = '';
if (!isset($text)) {
	$curl = curl_init("https://graph.facebook.com/v2.4/{$page_id}?fields=name,link,feed.limit({$count})%7Bstory,message,created_time,updated_time,from,link,name,caption,description,attachments%7D&access_token={$access_token}");
	curl_setopt($curl, CURLOPT_HEADER, True);
	curl_setopt($curl, CURLOPT_FAILONERROR, True);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, True);
	if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
		curl_setopt($curl, CURLOPT_HTTPHEADER, array('If-None-Match: ' . $_SERVER['HTTP_IF_NONE_MATCH']));
	}
	$response = curl_exec($curl);
	$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
	if (!$response) {
		exit($http_code . '  ' . curl_error($curl));
	}
	$header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
	$header = substr($response, 0, $header_size);
	$text = substr( $response, $header_size );
	curl_close($curl);
	if (preg_match('/^ETag: (.*)$/m', $header, $matches)) {
		$etag = $matches[1];
	}
	if ($http_code == 304) {
		header('HTTP/1.1 304 Not Modified');
		header('Content-type: application/atom+xml');
		if ($etag != NULL) header('ETag: ' . $etag);
		exit();
	}
}

$data = json_decode($text, True);
//print_r($data);
//exit();

function dateTo3339($dateString) {
	// Facebook's dates are almost RFC-3339 compliant except for the missing ':' in the offset
	return preg_replace('/([+-]\d\d)(\d\d)$/', '$1:$2', $dateString);
}

function formatAttachments($attachments) {
	$htmltext = '<ul>';
	foreach ($attachments as $attachment) {
		$htmltext .= '<li><a href="' . htmlspecialchars(isset($attachment['url']) ? $attachment['url'] : '') . '">';
		if (isset($attachment['title']) && $attachment['title'] != NULL) {
			$htmltext .= htmlspecialchars($attachment['title']) . '</a>';
			if (isset($attachment['type']) && $attachment['type'] != NULL) {
				$htmltext .= ' (' . htmlspecialchars($attachment['type']) . ')';
			}
		}
		else {
			$htmltext .= htmlspecialchars((isset($attachment['type']) && $attachment['type'] != NULL) ? $attachment['type'] : 'Link') . '</a>';
		}
		if (isset($attachment['media']) && isset($attachment['media']['image'])) {
			$image = $attachment['media']['image'];
			if (isset($image['src']) && $image['src'] != NULL) {
				$htmltext .= ' – <a href="' . htmlspecialchars($image['src']) . '">image';
				if (isset($image['width']) && $image['width'] != NULL && isset($image['height']) && $image['height'] != NULL) {
					$htmltext .= " ({$image['width']}×{$image['height']})";
				}
				$htmltext .= '</a>';
			}
		}
		if (isset($attachment['subattachments']) && isset($attachment['subattachments']['data'])) {
			$htmltext .= formatAttachments($attachment['subattachments']['data']);
		}
		$htmltext .= '</li>';
	}
	$htmltext .= '</ul>';
	return $htmltext;
}

mb_internal_encoding('UTF-8');

header('Content-type: application/atom+xml');
if ($etag != NULL) header('ETag: ' . $etag);

print('<?xml version="1.0" encoding="utf-8"?>' . "\n");
print('<feed xmlns="http://www.w3.org/2005/Atom">' . "\n");

// "" == NULL (but not === NULL)
print('	<title>Facebook / ' . htmlspecialchars($data['name']) . '</title>' . "\n");
print('	<link href="' . htmlspecialchars($data['link']) . '"/>' . "\n");
print('	<link rel="self" type="application/atom+xml" href="http://' . htmlspecialchars($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) . '"/>' . "\n");
print('	<id>https://www.facebook.com/' . htmlspecialchars($data['id']) . '</id>' . "\n");
print('	<icon>https://fbstatic-a.akamaihd.net/rsrc.php/yl/r/H3nktOa7ZMg.ico</icon>' . "\n");
print('	<author><name>' . htmlspecialchars($data['name']) . '</name></author>' . "\n");
print('	<updated>' . htmlspecialchars(dateTo3339($data['feed']['data'][0]['updated_time'])) . '</updated>' . "\n"); // first entry seems to be newest (maybe sort explicitly to be sure)

foreach ($data['feed']['data'] as $post) {

	$htmltext = '';
	if (isset($post['story']) && $post['story'] != NULL) { // isset() includes a check for !== NULL but not for != NULL ("" == NULL)
		$htmltext .= '<p style="font-style: italic;">' . htmlspecialchars($post['story']) . '</p>';
	}
	if (isset($post['message']) && $post['message'] != NULL) {
		$htmltext .= '<p>' . str_replace("\n", '<br/>', htmlspecialchars($post['message'])) . '</p>';
	}
	if (isset($post['link']) && $post['link'] != NULL) {
		$name = 'Link';
		if (isset($post['name']) && $post['name'] != NULL) {
			$name = $post['name'];
		}
		$htmltext .= '<p><a href="' . htmlspecialchars($post['link']) . '"';
		if (isset($post['caption']) && $post['caption'] != NULL) {
			$htmltext .= ' title="' . htmlspecialchars($post['caption']) . '"';
		}
		$htmltext .= '>' . htmlspecialchars($name) . '</a>';
		if (isset($post['description']) && $post['description'] != NULL) {
			$htmltext .= ' ' . htmlspecialchars($post['description']);
		}
		$htmltext .= '</p>';
	}
	if (isset($post['attachments']) && isset($post['attachments']['data'])) {
		$htmltext .= formatAttachments($post['attachments']['data']);
	}

	print('	<entry>' . "\n");
	if (isset($post['from']) && isset($post['from']['name']) && $post['from']['name'] != NULL) {
		print('		<author><name>' . htmlspecialchars($post['from']['name']) . '</name></author>' . "\n");
	}
	// this redirects to the human-readable URL of the post, so far I haven't found a way of obtaining that directly
	print('		<link href="https://www.facebook.com/' . htmlspecialchars($post['id']) . '"/>' . "\n");
	print('		<id>https://www.facebook.com/' . htmlspecialchars($post['id']) . '</id>' . "\n");
	$title = 'Post';
	if (isset($post['message']) && $post['message'] != NULL) {
		$title = $post['message'];
	}
	else if (isset($post['story']) && $post['story'] != NULL) {
		$title = $post['story'];
	}
	if (mb_strlen($title) > 80) {
		$title = mb_substr($title, 0, 80) . '…';
	}
	print('		<title>' . htmlspecialchars($title) . '</title>' . "\n");
	print('		<published>' . htmlspecialchars(dateTo3339($post['created_time'])) . '</published>' . "\n");
	print('		<updated>' . htmlspecialchars(dateTo3339($post['updated_time'])) . '</updated>' . "\n");
	print('		<content type="html">' . htmlspecialchars($htmltext) . '</content>' . "\n");
	print('	</entry>' . "\n");
}

print('</feed>' . "\n");

?>
