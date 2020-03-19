<?php 

// src/Controller/LuckyController.php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\CssSelector\CssSelectorConverter;
use App\Entity\Video;
use App\Entity\UserData;

use Symfony\Component\Validator\Constraints as Assert;

class DefaultController extends Controller
{
    public function index()
    {
      
        return $this->render('index.html.twig',[
        	'error' => '',
        ]);
    }
    public function parse( Request $request )
    {
    	$api_key = 'AIzaSyBQgtC-k5YZQPjznhQkqg0k0hey9u1_sdI';
    	if (filter_var($request->request->get('link'), FILTER_VALIDATE_URL) === FALSE) {
		    return $this->render('index.html.twig', [
	            'error' => 'Not a valid URL',
	        ]);
		}
    	$em1 = $this->getDoctrine()->getManager();
    	$user_ip = $this->container->get('request_stack')->getCurrentRequest()->getClientIp();
    	$browser_info =  $this->getBrowser();
    	$browser = $browser_info['name'] . ' '.$browser_info['version']; 
		$datetime =  new \DateTime();
		$date = $datetime->format('Y-m-d H:i:s');
		$userData = new UserData();
		$userData->setUserIp($user_ip);
		$userData->setUserBrowser($browser);
		$userData->setTime($datetime);
		$em1->persist($userData);
        $em1->flush();
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $request->request->get('link'));
		curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$result = curl_exec($ch);
		if (curl_error($ch)) {
		    $error_msg = curl_error($ch);
		    return $this->render('index.html.twig', [
	            'error' => $error_msg,
	        ]);
		}
		curl_close($ch);
		$crawler = new Crawler($result);
		$result = $crawler
        	->filter('div.item');
		$elements = array();
		$file = fopen('export.csv', 'w');
		fputcsv($file, array('Title', 'Old title', 'Video iframe', 'Old iframe', 'Image', 'Old image', 'Description', 'Video key'));
		$total_count = 0;
		$new_count = 0;
		$edit_count = 0;

		foreach($result as $key => $element) {
			$div_element = new Crawler($element);
			
			$image = 'https://lexani.com/'.$div_element->filter('img')->attr('src');

			$video_key = $div_element->attr('data-src');
			$video_iframe = trim('https://www.youtube.com/embed/'.$video_key);

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, 'https://www.googleapis.com/youtube/v3/videos?part=snippet%2CcontentDetails%2Cstatistics&id='.$video_key.'&key='.$api_key);
			curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$result = curl_exec($ch);
			//*var_dump($result);

			if (curl_error($ch)) {
			   $description = 'error while opening'.$youtube_link;

			} else {
				$result = json_decode($result, true);			
				if( !empty($result['items']) ) {
					$description = $result['items'][0]['snippet']['description'];
				} else {
					$description = 'Video is not available!';
				}
			}
			curl_close($ch);
	   		$title = $div_element->filter('p.thumbnail-title')->text();
	   		$elements[$key][0] = $title;
	   		$elements[$key][1] = '';
	   		$elements[$key][2] = $video_iframe;
	   		$elements[$key][3] = '';
	   		$elements[$key][4] = $image;
	   		$elements[$key][5] = '';
			$elements[$key][6] = $description;
			$elements[$key][7] = '';
			$elements[$key][8] = $video_key;
			   //*var_dump($elements[$key][8]);
		   }
		   
		foreach ($elements as $key => $row) {
			$total_count++;
		 	$em = $this->getDoctrine()->getManager();

		 	$repository = $this->getDoctrine()->getRepository(Video::class);
			$video = $repository->findOneBy(['video_key' => $row[6]]);
			if( $video == null ) {
				$em = $this->getDoctrine()->getManager();
		        $videoInstance = new Video();
		        $videoInstance->setTitle( $row[0] );
		        $videoInstance->setIframe( $row[2] );
		        $videoInstance->setImage( $row[4] );
		        $videoInstance->setDescription( $row[6] );
		        $videoInstance->setVideoKey( $row[8] );
		        $videoInstance->setOldTitle( '' );
		        $em->persist($videoInstance);
		        $em->flush();
		        $new_count++;
			} else {
				$edit = false;
				if( $video->getTitle() != $row[0] ) {
					$row[1] = $video->getTitle();
					$edit = true;
				}
				if( $video->getIframe() != $row[2] ) {
					$row[3] = $video->getIframe();
					$edit = true;
				}
				if( $video->getImage() != $row[4] ) {
					$row[5] = $video->getImage();
					$edit = true;
				}
				if( $video->getDescription() != $row[6] ) {
					$row[7] = $video->getDescription();
					$edit = true;
				}
				if( $edit === true ) {
					$edit_count++;
				}
				$row[5] = $video->getImage();
				
				$video->setTitle($row[0]);
				$video->setIframe($row[2]);
				$video->setImage($row[4]);
				$video->setDescription($row[6]);
				$video->setVideoKey($row[8]);
				$video->setOldTitle($row[1]);
				$em->flush();

			}
			
			fputcsv($file, $row);
			$elements_to_view[$key]['title'] = $row[0];

	   		$elements_to_view[$key]['video_iframe'] = $row[1];
	   		$elements_to_view[$key]['image'] = $row[2];
		}

		
		return $this->render('output.html.twig', [
            'elements_to_view' => $elements_to_view,
            'total_found' => $total_count,
            'new_found' => $new_count,
            'edit_found' => $edit_count,
        ]);
        
    }

    private function getBrowser() {
		$u_agent = $_SERVER['HTTP_USER_AGENT'];
		$bname = 'Unknown';
		$platform = 'Unknown';
		$version= "";
		if (preg_match('/linux/i', $u_agent)) {
			$platform = 'linux';
		} elseif (preg_match('/macintosh|mac os x/i', $u_agent)) {
			$platform = 'mac';
		} elseif (preg_match('/windows|win32/i', $u_agent)) {
			$platform = 'windows';
		}
		if(preg_match('/MSIE/i',$u_agent) && !preg_match('/Opera/i',$u_agent)) {
			$bname = 'Internet Explorer';
			$ub = "MSIE";
		} elseif(preg_match('/Firefox/i',$u_agent)) {
			$bname = 'Mozilla Firefox';
			$ub = "Firefox";
		} elseif(preg_match('/Chrome/i',$u_agent)) {
			$bname = 'Google Chrome';
			$ub = "Chrome";
		} elseif(preg_match('/Safari/i',$u_agent)) {
			$bname = 'Apple Safari';
			$ub = "Safari";
		} elseif(preg_match('/Opera/i',$u_agent)) {
			$bname = 'Opera';
			$ub = "Opera";
		} elseif(preg_match('/Netscape/i',$u_agent)) {
			$bname = 'Netscape';
			$ub = "Netscape";
		}
		$known = array('Version', $ub, 'other');
		$pattern = '#(?<browser>' . join('|', $known) . ')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';
		if (!preg_match_all($pattern, $u_agent, $matches)) {
		}
		$i = count($matches['browser']);
		if ($i != 1) {
		if (strripos($u_agent,"Version") < strripos($u_agent,$ub)){
			$version= $matches['version'][0];
		} else {
			$version= $matches['version'][1];
		}
		} else {
			$version= $matches['version'][0];
		}
		if ($version==null || $version=="") {$version="?";}
		return array(
			'userAgent' => $u_agent,
			'name'      => $bname,
			'version'   => $version,
			'platform'  => $platform,
			'pattern'    => $pattern
		);
	}
}