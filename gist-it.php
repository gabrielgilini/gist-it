<?php
/*
Plugin Name: gist-it
Plugin URI: http://pomoti.com/gist-it
Description: Easy posting of code and syntax highlights via <a href="http://gist.github.com/">Gist</a>.
Author: Dirceu Jr
Version: 0.1
Author URI: http://pomoti.com/sobre-os-autores#Dirceu

Copyright (C) 2008 Dirceu Jr

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.

*/

// post code, return gist-id
function gi_postGist( $txt, $lang, $ch ) {
	// send
	curl_setopt($ch, CURLOPT_URL, 'http://gist.github.com/gists');
	$post['file_name[gistfile1]']	= '';
	$post['file_contents[gistfile1]']	= gi_codeFormat($txt);
	$post['file_ext[gistfile1]']	= '.'.gi_mapFormat($lang);
	
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
	$result = curl_exec($ch);
	
	// retrive id
	preg_match('/http:\/\/gist\.github\.com\/([^"]*)/', $result, $id);
	
	// return 0 if /error/
	if (count($id) == 2) {
		return $id[1];
	} else {
		return 0;
	}
}

// clear code
// HEY: that probably will crash something at some point
function gi_codeFormat( $code ) {
	$code = str_replace('\\\'', '\'', $code);
	$code = str_replace('\"', '"', $code);
	return $code;
}

// translate geeky language to more geeky language
function gi_mapFormat( $format ) {
	$sugar = array('ruby','rb','c#','c-sharp','csharp','delphi','pascal','python','py','jscript','javascript','vb.net');
	$salt = array('rbx','rbx','cs','cs','cs','pas','pas','sc','sc','js','js','bas');
	
	if (array_search($format, $sugar)) {
		$format = $salt[$has];
	}
	
	return $format;
}

// match [sourcecode]. post to postGist. replace to [gist='GIST-ID']
function gi_matchSrc( $content ) {
	// start curl session
	$ch = curl_init();
	
	if (get_option('gi_login') == '' || get_option('gi_password')) {
		// login into gist
		curl_setopt($ch, CURLOPT_URL, 'https://gist.github.com/session');
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 20);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookie.txt');
		curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookie.txt');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$login_post['login']	= get_option('gi_login');
		$login_post['password']	= get_option('gi_password');
		curl_setopt($ch, CURLOPT_POSTFIELDS, $login_post);
		curl_exec($ch);
	}
	
	$regex = '/\[(sourcecod|sourc|cod)(e language=|e lang=|e=)';
	$regex .= '\\\\';
	$regex .= '([\'"])([^\'"]*)';
	$regex .= '\\\\';
	$regex .= '\3\](.*?)\[\/\1e\]/si';
	
	preg_match_all( $regex, $content, $matches, PREG_SET_ORDER );
	
	foreach($matches as $match) {
		$gistID = gi_postGist($match[5], $match[4], $ch);
		if ($gistID != 0) {
			$content = str_replace($match[0], '[gist=\''.$gistID.'\']', $content);
		}
	}
	
	// close curl
	curl_close($ch);
	
	return $content;	
}

// match [gist='GIST-ID'] and replace for JS
function gi_replaceSrc( $content ) {
	$regex = '/\[gist=([\'"])([^\'"]*)\1\]/si';
	
	preg_match_all( $regex, $content, $matches, PREG_SET_ORDER );	
	
	foreach($matches as $match) {
		$content = str_replace($match[0], '<script src="http://gist.github.com/'.$match[2].'.js"></script>', $content);
	}
	
	return $content;
}

function gi_config() {
	if (isset($_POST['gi_login']) && isset($_POST['gi_password'])) {
		update_option('gi_login', $_POST['gi_login']);
		update_option('gi_password', $_POST['gi_password']);
	}
	?>
	<div class="wrap">
		<h2>GitHub Account</h2>
		<form method="post">
			<p>
				<label for="gi_login">Login</label> <br/>
				<input name="gi_login" type="text" value="<?php echo get_option('gi_login'); ?>" size="25" />
			</p>
			<p>
				<label for="gi_password">Password</label> <br/>
				<input name="gi_password" type="password" value="<?php echo get_option('gi_password'); ?>" size="25" />
			</p>
			<div class="submit"><input type="submit" name="gi_github" value="Go!" /></div>
		</form>
	</div>
<?php
} // end of config page

function gi_addconfig() {
	if ( function_exists('add_submenu_page') ) {
		add_submenu_page('plugins.php', 'gist-it settings', 'gist-it settings', 'manage_options', 'gist-it', 'gi_config');
	}
}

// VRUUUUUM
add_action( 'content_save_pre', 'gi_matchSrc' );
add_action( 'the_content', 'gi_replaceSrc' );
add_action('admin_menu', 'gi_addconfig');

/*

Plain Text: .txt
ActionScript: .as
Bash: .sh
C: .c
C++: .cpp
CSS: .css
Diff: .diff
Erlang: .erl
HTML: .html
Haskell: .hs
Io: .io
Java: .java
JavaScript: .js
Lua: .lua
OCaml: .ml
Objective-C: .m
PHP: .php
Perl: .pl
Python: .sc
RHTML: .rhtml
Ruby: .rbx
SQL: .sql
Scheme: .scm
Smalltalk: .st
Smarty: .tpl
XML: .xml
Batchfile: .cmd
Befunge: .befunge
Boo: .boo
Brainfuck: .b
C#: .cs
Common Lisp: .el
D: .di
Darcs Patch: .darcspatch
Delphi: .pas
Dylan: .dylan
Fortran: .f90
GAS: .S
Genshi: .kid
Gettext Catalog: .po
Groff: .man
HTML+PHP: .phtml
INI: .properties
IRC logs: .weechatlog
Java Server Page: .jsp
LLVM: .ll
Literate Haskell: .lhs
Logtalk: .lgt
MOOCode: .moo
Makefile: .mak
Mako: .mao
Matlab: .matlab
MiniD: .md
MuPAD: .mu
Myghty: .myt
NumPy: .sc
Python Traceback: .pytb
Raw token data: .raw
Redcode: .cw
S: .R
Tcl: .tcl
Tcsh: .csh
TeX: .tex
Text only: .txt
VB.net: .bas
VimL: .vim
XSLT: .xslt
c-objdump: .c-objdump
cpp-objdump: .cxx-objdump
d-objdump: .d-objdump
objdump: .objdump
reStructuredText: .rest

*/

?>