<?php
/*
Plugin Name: gist-it
Plugin URI: http://pomoti.com/gist-it
Description: Easy posting of code and syntax highlights via <a href="http://gist.github.com/">Gist</a>.
Author: Dirceu Jr
Version: 0.2
Author URI: http://pomoti.com/sobre-os-autores#Dirceu

Copyright (c) 2008 Dirceu Jr

Permission is hereby granted, free of charge, to any person
obtaining a copy of this software and associated documentation
files (the "Software"), to deal in the Software without
restriction, including without limitation the rights to use,
copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the
Software is furnished to do so, subject to the following
conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
OTHER DEALINGS IN THE SOFTWARE.

*/

// post code, return gist-id
function gi_postGist( $id = '', $txt, $lang, $ch ) {
	// put
	if ($id == '') {
		$post['file_name[gistfile1]']		= '';
		$post['file_contents[gistfile1]']	= gi_codeFormat($txt);
		$post['file_ext[gistfile1]']		= '.'.gi_mapFormat($lang);
		curl_setopt($ch, CURLOPT_URL, 'http://gist.github.com/gists');
	} else { // edit
		$post['file_name[gistfile1.'.$lang.']']		= '';
		$post['_method']							= 'put';
		$post['file_contents[gistfile1.'.$lang.']']	= gi_codeFormat($txt);
		$post['file_ext[gistfile1.'.$lang.']']		= '.'.gi_mapFormat($lang);
		curl_setopt($ch, CURLOPT_URL, 'http://gist.github.com/gists/'.$id);
	}
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
	$result = curl_exec($ch);
	
	// retrive id
	preg_match('/http:\/\/gist\.github\.com\/([^"]*)/', $result, $nid);
	
	// return 0 if /error/
	if (count($nid) == 2) {
		return $nid[1];
	} else {
		return 0;
	}
}

// HEY: that's stupid
function gi_codeFormat( $code ) {
	$code = str_replace('\\\'', '\'', $code);
	$code = str_replace('\"', '"', $code);
	return $code;
}

// translate geeky language to more geek language
function gi_mapFormat( $format ) {
	$sugar = array('ruby','rb','c#','c-sharp','csharp','delphi','pascal','python','py','jscript','javascript','vb.net');
	$salt = array('rbx','rbx','cs','cs','cs','pas','pas','sc','sc','js','js','bas');
	
	$has = array_search($format, $sugar);
	if ($has) {
		$format = $salt[$has];
	}
	
	return $format;
}

// match [sourcecode]. post to gist
function gi_matchGist( $content ) {
	// start curl session
	$ch = curl_init();
	
	if (get_option('gi_login') && get_option('gi_password')) {
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
	$regex .= '\\\\[\'"]([^\'"\\\\]*)';
	$regex .= '([^\]]*gist=\\\\[\'"]([^\'"\\\\]*))?';
	$regex .= '[^\]]*\]([^\]]*)\[\/\1e\]/si';
	
	preg_match_all( $regex, $content, $matches, PREG_SET_ORDER );
	
	foreach($matches as $match) {
		$gistID = gi_postGist($match[5], $match[6], $match[3], $ch);
		if ($gistID != 0 && $match[5] == '') {
			$content = preg_replace('/\['.$match[1].$match[2].'\\\\[\'"]'.$match[3].'\\\\[\'"]\]/', '['.$match[1].$match[2].'"'.$match[3].'" gist="'.$gistID.'"]', $content);
		}
	}
	
	// close curl
	curl_close($ch);
	
	return $content;	
}

function gi_updateFromGist($content) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_TIMEOUT, 20);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	
	$regex = '/\[(sourcecod|sourc|cod)(e language=|e lang=|e=)';
	$regex .= '[\'"]([^\'"]*)';
	$regex .= '([^\]]*gist=[\'"]([^\'"]*))';
	$regex .= '[^\]]*\]([^\]]*)\[\/\1e\]/si';
	
	preg_match_all( $regex, $content, $matches, PREG_SET_ORDER );
	
	foreach($matches as $match) {
		curl_setopt($ch, CURLOPT_URL, 'http://gist.github.com/'.$match[5].'.txt');
		$src = curl_exec($ch);
		
		$content = str_replace($match[0], '['.$match[1].$match[2].'"'.$match[3].'" gist="'.$match[5].'"]'.$src.'[/'.$match[1].'e]', $content);
	}

	curl_close($ch);
	return $content;
}

// match gist='GIST-ID' and replace for JS
function gi_showJS( $content ) {
	$regex = '/\[(sourcecod|sourc|cod)(e language=|e lang=|e=)';
	$regex .= '[\'"]([^\'"]*)';
	$regex .= '([^\]]*gist=[\'"]([^\'"]*))?';
	$regex .= '[^\]]*\]([^\]]*)\[\/\1e\]/si';
	
	preg_match_all( $regex, $content, $matches, PREG_SET_ORDER );
	
	foreach($matches as $match) {
		$content = str_replace($match[0], '<script src="http://gist.github.com/'.$match[5].'.js"></script>', $content);
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
add_action( 'content_edit_pre', 'gi_updateFromGist' );
add_action( 'content_save_pre', 'gi_matchGist' );
add_action( 'the_content', 'gi_showJS' );
add_action( 'admin_menu', 'gi_addconfig' );
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