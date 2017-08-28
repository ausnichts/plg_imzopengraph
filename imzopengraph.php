<?php
// No direct access
defined( '_JEXEC' ) or die;

class plgSystemImzOpenGraph extends JPlugin {

	protected $autoloadLanguage = true;
	
	private $type;
	private $ogData = array();
	private $twData = array();

	function onContentPrepareForm($form, $data) {

		$app = JFactory::getApplication();
		
		if(!$app->isAdmin()) {
			return;
		}
		
		$option = $app->input->get('option');

 
		if($option === 'com_content') {

			$xml = simplexml_load_file( __DIR__ . '/forms/content.xml');
			$xml->fields->fieldset->field[0]['default'] = $this->params->get('og_content_default');
			$xml->fields->fieldset->field[6]['default'] = $this->params->get('tw_content_default');
			$xml->fields->fieldset->field[7]['default'] = $this->params->get('tw_card');
			$form->load($xml);

		} else if($option === 'com_menus') {
			JForm::addFormPath(__DIR__ . '/forms');
			$form->loadFile('menu', false);
		}

	}


	public function onBeforeCompileHead() {

		$app = JFactory::getApplication();

		if($app->isAdmin()) {
			return;
		}

		$option = $app->input->get('option', '');
		$view = $app->input->get('view', '');
		$scope = $option . '.' . $view;
//		var_dump($scope);

		$isHome = false;
		$menus = $app->getMenu();
		$thisMenu = $menus->getActive();
		if ($thisMenu === $menus->getDefault()) {
			$isHome = true;
		}

		$doc = JFactory::getDocument();
		$globalSitename = JFactory::getConfig()->get('sitename');
		$globalDesc = $doc->description;
		
		if($isHome) {

			$this->type = $this->params->get('imzopengraphtype');
			$this->setOgData('og:type', $this->type);
			$tmp = !empty($this->params->get('imzopengraphtitle')) ? $this->params->get('imzopengraphtitle') : $globalSitename;
			$this->setOgData('og:title', $tmp);
			if (!empty($this->params->get('imzopengraphimage'))) $this->setOgData('og:image', JURI::base(false) . $this->params->get('imzopengraphimage'));
			$this->setOgData('og:url', JURI::base(false));
			$tmp = !empty($this->params->get('imzopengraphdesc')) ? $this->params->get('imzopengraphdesc') : $globalDesc;
			$this->setOgData('og:description', $tmp);
			$tmp = !empty($this->params->get('imzopengraphsitename')) ? $this->params->get('imzopengraphsitename') : $globalSitename;
			$this->setOgData('og:site_name', $tmp);
			
			$this->setTwData('twitter:card', $this->params->get('tw_card'));
			if (!empty($this->params->get('tw_site'))) $this->setTwData('twitter:site', $this->params->get('tw_site'));
			if (!empty($this->params->get('tw_creator'))) $this->setTwData('twitter:creator', $this->params->get('tw_creator'));
			
		} elseif($scope !== 'com_content.article' && !empty($thisMenu)) {

			if ($thisMenu->params->get('og_active')) {

				$this->type = $thisMenu->params->get('og_type');
				$this->setOgData('og:type', $this->type);
				$tmp = !empty($thisMenu->params->get('og_title')) ? $thisMenu->params->get('og_title') : $doc->getTitle();
				$this->setOgData('og:title', $tmp);

				$tmp = "";
				if(!empty($thisMenu->params->get('og_image'))) {
					$tmp = JURI::base() . $thisMenu->params->get('og_image');
				}else{
					//
				}
				if(empty($tmp) && !empty($this->params->get('imzopengraphimage'))) {
					$tmp = JURI::base() . $this->params->get('imzopengraphimage');
				}
				$this->setOgData('og:image', $tmp);

				$this->setOgData('og:url', JURI::getInstance()->toString());

				$tmp= "";
				if(!empty($thisMenu->params->get('og_description'))) {
					$tmp = $thisMenu->params->get('og_description');
				} else {
					//
				}
				if(empty($tmp)) {
					$tmp = !empty($this->params->get('imzopengraphdesc')) ? $this->params->get('imzopengraphdesc') : $globalDesc;
				}
				$this->setOgData('og:description', $tmp);

			}

			if ($thisMenu->params->get('tw_active')) {
				$this->setTwData('twitter:card', $thisMenu->params->get('tw_card'));
				$tmp = !empty($thisMenu->params->get('tw_site')) ? $thisMenu->params->get('tw_site') : $this->params->get('tw_site');
				$this->setTwData('twitter:site', $tmp);
				$tmp = !empty($thisMenu->params->get('tw_creator')) ? $thisMenu->params->get('tw_creator') : $this->params->get('tw_creator');
				$this->setTwData('twitter:creator', $tmp);
			}
		}
		
		if (!empty($this->ogData)) $this->setOgData('fb:app_id', $this->params->get('imzopengraphfbappid'));

		$doc->addCustomTag('<!-- IMUZA Open Graph Plugin -->');
		foreach($this->twData as $name => $value) {
			$doc->addCustomTag('<meta name="' . $name . '" content="' . $value . '"/>');
		}
		foreach($this->ogData as $name => $value) {
			$doc->addCustomTag('<meta property="' . $name . '" content="' . $value . '"/>');
		}
		$doc->addCustomTag('<!-- end IMUZA Open Graph Plugin -->');

	}

	public function onAfterRender() {

		if (empty($this->type)) {
			return;
		}
		
		$buf = JResponse::getBody();

		$ogPrefix = 'og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# ' . $this->type . ': http://ogp.me/ns/' . $this->type . '#';

		if(strpos($buf, '<head>') !== false) {
			$buf = preg_replace('/<head>/', '<head prefix="' . $ogPrefix . '">', $buf, 1);
		} else {
			$buf = preg_replace('/<head /', '<head prefix="' . $ogPrefix . '" ', $buf, 1);
		}

		JResponse::setBody($buf);

	}

	function onContentBeforeDisplay($context, &$article, &$params, $limitstart) {

		if($context !== 'com_content.article') {
			return;
		}
		
		if ($article->params->get('og_active') || $this->params->get('og_content_default')) {

			$tmp = !empty($article->params->get('og_type')) ? $article->params->get('og_type') : 'article';
			$this->setOgData('og:type', $tmp);
			$this->type = $tmp;

			$tmp = !empty($article->params->get('og_title')) ? $article->params->get('og_title') : $article->title;
			$this->setOgData('og:title', $tmp);

			$tmp = "";
			if(!empty($article->params->get('og_image'))) {
				$tmp = JURI::base() . $article->params->get('og_image');
			}else{
				$images = json_decode($article->images);
				if(!empty($images->image_intro) || !empty($images->image_fulltext)) {
					$tmp = !empty($images->image_intro) ? JURI::base() . $images->image_intro : JURI::base() . $images->image_fulltext;
				}else{
					$fulltext = $article->introtext . $article->fulltext;
					$flag = preg_match('/<img[^src]*src\s*=\s*[\"\']([^\"\']*).*>/i', $fulltext, $matches);
					if($flag){
						$tmp = JURI::base() . $matches[1];
					}
				}
			}
			if(empty($tmp) && !empty($this->params->get('imzopengraphimage'))) {
				$tmp = JURI::base() . $this->params->get('imzopengraphimage');
			}
			$this->setOgData('og:image', $tmp);

			$this->setOgData('og:url', JURI::getInstance()->toString());
			if(!empty($article->params->get('og_description'))) {
				$this->setOgData('og:description', $article->params->get('og_description'));
			} else {
				$tmp = strip_tags($article->introtext);
				$tmp = str_replace(array(PHP_EOL, "\t"), array(' ', ' '), $tmp);
				$tmp = mb_strimwidth( $tmp, 0, 240, "...", "UTF-8" );
				$this->setOgData('og:description', $tmp);
			}
		}

		if ($article->params->get('tw_active') || $this->params->get('tw_content_default')) {
			$tmp = !empty($article->params->get('tw_card')) ? $article->params->get('tw_card') : $this->params->get('tw_card');
			$this->setTwData('twitter:card', $tmp);
			$tmp = !empty($article->params->get('tw_site')) ? $article->params->get('tw_site') : $this->params->get('tw_site');
			$this->setTwData('twitter:site', $tmp);
			$tmp = !empty($article->params->get('tw_creator')) ? $article->params->get('tw_creator') : $this->params->get('tw_creator');
			$this->setTwData('twitter:creator', $tmp);
		}
	}

	private function setOgData($name, $value) {

		if(!empty($value)) {
			$this->ogData[$name] = $value;
		}

	}

	private function setTwData($name, $value) {

		if(!empty($value)) {
			$this->twData[$name] = $value;
		}

	}

}