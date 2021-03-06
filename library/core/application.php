<?php

/*
Copyright 2014 - Nicolas Devenet <nicolas@devenet.info>

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.

Code source hosted on https://github.com/Devenet/MoodPicker
*/

namespace Core;

use Rain\Tpl;
use Core\Config;
use Core\Token;
use Utils\Menu;
use Utils\Cookie;
use Utils\Session;
use Utils\TextHelper;
use Database\SQLite;
use Manage\Authentification;


final class Application {

  private $theme;
  private $template;
  private $page;
  private $fake_page;
  private $request;
  private $modules;
  private $url;
  private $auth;
  private $installed = TRUE;

  private $timestart;

  const VERSION = '1.2.0';

  public function __construct() {
    $this->timestart = microtime(true);

    $this->checkRequirements();

    $this->theme = Theme::Instance();
    $this->template = new Tpl();
    $this->template->configure(array(
      'auto_escape' => false,
      'cache_dir' => Config::Path('data/cache/'),
      'tpl_dir' => Config::Path('templates/'),
      'debug' => Config::Get('debug')
    ));
    $this->modules = array();

    session_name(Setting::APP_NAME.'_'.md5($_SERVER['SCRIPT_NAME']));
    session_start();

    $this->auth = new Authentification();

    $this->request = preg_split('-/-', isset($_GET['page']) ? $_GET['page'] : '');
    if ($this->request[0] == 'index') { header('Location: ./'); }
    if (!$this->installed)  { $this->request = ['manage', 'installation']; }
    $this->page = empty($this->request[0]) ? 'index' : str_replace("\0", '', htmlspecialchars($this->request[0]));
    $this->fake_page = NULL;

    $this->url = mb_substr($_SERVER['SCRIPT_NAME'], 0, mb_strlen($_SERVER['SCRIPT_NAME'])-9);
    $this->template->assign('URL', $this->url);

    $this->checkTheme();
  }

  // check requirements
  private function checkRequirements() {
    // is SQLite3 available
    if (! class_exists('SQLite3', false)) { die('<p>Holy crap! The PHP extension <strong>SQLite3</strong> is not actived or installed.</p>'); }
    // can we write
    if (! is_writable(Config::DIR_DATA)) { die('<p>Holy crap! Application does not have the right to write in its own directory <code>'.Config::DIR_DATA.'</code>.</p>'); }
    // is the apps installed
    if (! is_file(Config::Path(Config::DIR_DATA.'/installed'))) { $this->installed = FALSE; }
  }

  // get URL base
  public function URL($file = '') {
    return $this->url.$file;
  }

  // return requested url
  public function request($key = NULL) {
    if (is_null($key)) { return array_map('htmlspecialchars', $this->request); }
    if (array_key_exists($key, $this->request)) { return htmlspecialchars($this->request[$key]); }
    return NULL;
  }

  // register a module
  public function register($module, $data) {
    switch($module) {
      case 'script':
      case 'script_file':
      $this->modules[$module][] = $data;
      break;
      default:
      $this->modules[$module] = $data;
    }
  }
  // assign a variable for template
  public function assign($name, $value) {
    $this->template->assign($name, $value);
  }

  // assign a sweety name for the current page used for active items or menus
  public function fakePage($page) {
    $this->fake_page = $page;
  }
  // change page to load
  public function page($page) {
    $this->page = str_replace('/', DIRECTORY_SEPARATOR, $page);
  }

  // check and update theme
  public function checkTheme() {
    // want to update ?
    if (isset($_GET['theme'])) {
      $this->theme->setTheme(htmlspecialchars($_GET['theme']));
      Cookie::Add('theme', $this->theme->getTheme(), Cookie::MONTH);
      $this->template->clean(-1);
    }
    // is a theme already in cookie ?
    else if (Cookie::Exists('theme')) {
      $this->theme->setTheme(Cookie::Get('theme'));
    }
  }

  // let's party hard
  public function run() {
    $this->controllerPage();
    $this->buildPage();
    $this->buildDebug();
    $this->display();
  }

  // get controller page
  protected function controllerPage() {
    $GLOBALS['app'] = $this;
    $controller = Config::Path(DIRECTORY_SEPARATOR.Config::DIR_PAGES.DIRECTORY_SEPARATOR.$this->page.'.php');
    if (file_exists($controller)) { require_once $controller; }
  }

  // build theme
  protected function buildPage() {
    // theme
    $this->template->assign('theme', $this->theme->getTheme());
    $this->template->assign('themes', $this->theme->getThemes());

    // force to be a specific page
    $page = is_null($this->fake_page) ? $this->page : $this->fake_page;

    // infos
    $this->template->assign('app_name', (new Setting('app_name'))->getValueOrDefault());
    $this->template->assign('app_title', (new Setting('app_title'))->getValueOrDefault());
    $this->template->assign('app_description', (new Setting('app_description'))->getValueOrDefault());
    $this->template->assign('app_copyright', (new Setting('app_copyright'))->getValueOrDefault());
    $this->template->assign('app_robots', (new Setting('app_robots'))->getValueOrDefault());
    $this->template->assign('app_version', TextHelper::niceVersion(self::VERSION));
    $this->template->assign('app_api_version', TextHelper::niceVersion(\Picker\API::VERSION));
    $this->template->assign('app_full_version', self::VERSION);
    $this->template->assign('app_full_api_version', \Picker\API::VERSION);

    // user infos
    $this->template->assign('user_isLogged', $this->auth->isLogged());
    $this->template->assign('user_email', Session::Get(Authentification::SESSION_USER_EMAIL));

    // navbar
    if (! isset($this->modules[Menu::NAVBAR])) {
      $m = new Menu();
      $m->item('./', 'Home');
      $this->modules[Menu::NAVBAR] = $m;
    }
    $this->template->assign(Menu::NAVBAR, $this->modules[Menu::NAVBAR]->generate($this->url.$page));

    // navbar right
    if (isset($this->modules[Menu::NAVBAR_RIGHT])) {
      $this->template->assign(Menu::NAVBAR_RIGHT, $this->modules[Menu::NAVBAR_RIGHT]->generate($this->url.$page));
    }

    // javascript
    if (isset($this->modules['script_file'])) { $this->template->assign('script_files', $this->modules['script_file']); }
    if (isset($this->modules['script'])) { $this->template->assign('scripts', $this->modules['script']); }
  }


  // build debug info and alerts
  protected function buildDebug() {
    if (Config::Get('debug')) {
      $this->template->assign('db_access', SQLite::Access());
      $timeend = microtime(true);
      $this->template->assign('build_time', number_format(($timeend-$this->timestart)*1000, 2));
    }
    $this->template->assign('debug', Config::Get('debug'));
  }

  // draw the template or 404
  protected function display() {
    try {
      $this->template->draw($this->page);
    }
    catch (\Rain\Tpl\NotFoundException $ex) {
      if (Config::Get('debug')) {
        $tpl = $ex->getTrace()[0]['args'][0] == 404 ? htmlspecialchars($_GET['template']) : $ex->getTrace()[0]['args'][0];
        $this->assign('exception', $tpl);
      }
      header('HTTP/1.1 404 Not Found', TRUE, 404);
      $this->template->assign('navbar', $this->modules[Menu::NAVBAR]->generate('404'));
      $this->template->draw('_404');
    }
  }

  // token management
  public function getToken() {
    $token = Token::Generate();
    $this->template->assign('token', $token);
    return $token;
  }
  public function acceptToken() {
    if (isset($_POST['token']) && Token::Accept($_POST['token'])) { return TRUE; }
    // invalid token...
    header('HTTP/1.1 401 Unauthorized', TRUE, 401);
    $this->errorPage('Invalid security token', 'The received token was empty or invalid. <br />Are you sure that <em>Cookies</em> are enabled on your browser?');
    return FALSE;
  }

  public function getExtendedToken() {
    $token = Session::Exists('current_ext_token') ? Session::Get('current_ext_token') : Token::Generate(TRUE);
    Session::Add('current_ext_token', $token);
    $this->template->assign('extended_token', $token);
    return $token;
  }
  public function acceptExtendedToken($token) {
    if (Token::AcceptExtended($token)) {
      Session::Remove('current_ext_token');
      return TRUE;
    }
    // invalid token...
    header('HTTP/1.1 401 Unauthorized', TRUE, 401);
    $this->errorPage('Invalid security token', 'The received token was empty or invalid. <br />Are you sure that <em>Cookies</em> are enabled on your browser?');
    return FALSE;
  }
  public function RemoveExtendedToken($token) {
    Token::RemoveExtended($token);
  }

  // auth management
  public function canLogin() {
    if ($this->auth->isBanned()) {
      header('HTTP/1.1 403 Forbidden', TRUE, 403);
      $this->errorPage('You don’t have permission to access on this server', 'You have been banned. <br />If you think it’s an error please come back <strong>later</strong> and try again.', FALSE);
    }
    return TRUE;
  }
  public function requireAuth() {
    $this->canLogin();
    if (!$this->auth->isLogged()) {
      header('Location: '.$this->URL('authentification'));
      exit();
    }
  }

  // generic error page
  public function errorPage($title, $content, $shouldTryAgain = TRUE) {
    $this->template->assign('error_title', $title);
    $this->template->assign('error_content', $content.
    ($shouldTryAgain ? '<div class="espace-top">Please <a href="'.$_SERVER['REQUEST_URI'].'">try again</a>.</div>' : ''));
    $this->page = '_error';
    $this->run();
    exit();
  }

  public function hackAttempt() {
    $this->errorPage('Something bad happend', 'Yeah, something very bad happend. <br />Maybe you just wanted to hack the system?');
  }

}
