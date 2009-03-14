<?php
/*
======================================================================
CakePHP TypogrifyHelper, based on php-typogrify and PHP SmartyPants
======================================================================
Prettifies your web typography by preventing ugly quotes and 'widows' 
and providing CSS hooks to style some special cases.
======================================================================

CakePHP TypogrifyHelper Copyright (c) 2009, Dave Poole <http://www.zastica.com>

php-typogrify Copyright (c) 2007, Hamish Macpherson

php-typogriphy is a port of the original Python code by Christian Metts.

SmartyPants Copyright (c) 2003-2004 John Gruber <http://daringfireball.net>

PHP SmartyPants Copyright (c) 2004-2005 Michel Fortin <http://www.michelf.com/>

All rights reserved.
License information follows at the end of this file.
 */

class TypogrifyHelper extends AppHelper {
	
	public $SmartyPantsPHPVersion;
	public $SmartyPantsSyntaxVersion;
	public $smartypantsAttr;
	public $spTagsToSkip;
	
	/**
	 * Constructor - Initializes the object and sets up default values
	 */
	function TypogrifyHelper() {
		
		$this->SmartyPantsPHPVersion    = '1.5.1e'; # Fru 9 Dec 2005
		$this->SmartyPantsSyntaxVersion = '1.5.1';  # Fri 12 Mar 2004

		// Tags to skip:
		$this->spTagsToSkip = '<(/?)(?:pre|code|kbd|script|math)[\s>]';

		# Change this to configure.
		#  1 =>  "--" for em-dashes; no en-dash support
		#  2 =>  "---" for em-dashes; "--" for en-dashes
		#  3 =>  "--" for em-dashes; "---" for en-dashes
		#  See docs for more configuration options.
		$this->smartypantsAttr = "1";
	}
	
	/**
	 * Main Typogrify Function. Calls the other functions that process the various 
	 * charactersets
	 *
	 * @param $text The text string to Typogrify
	 * @param $doGuillemets Also process French-style << and >>?
	 *
	 * @return The typogrified text
	 */
	function parse($text, $doGuillemets = false) {
		$text = $this->amp( $text );
	    $text = $this->widont( $text );
	    $text = $this->smartyPants( $text );
	    $text = $this->caps( $text );
	    $text = $this->initial_quotes( $text, $doGuillemets );
	    $text = $this->dash( $text );

	    return $this->output($text);
	}
	
	
	/**
	 * Wraps ampersands in html with ``<span class="amp">`` so they can be
	 * styled with CSS. Ampersands are also normalized to ``&amp;``. Requires 
	 * ampersands to have whitespace or an ``&nbsp;`` on both sides.
	 * 
	 * It won't mess up & that are already wrapped, in entities or URLs
	 *
	 * @param   $text Text to transform ampersands in
	 *
	 * @return  The string with ampersands replaced
	 */
	function amp ($text) {
	    $ampFinder = "/(\s|&nbsp;)(&|&amp;|&\#38;|&#038;)(\s|&nbsp;)/";
	    return preg_replace($ampFinder, '\\1<span class="amp">&amp;</span>\\3', $text);
	}
	
	/**
	 * Replaces the space between the last two words in a string with ``&nbsp;``
	 * Works in these block tags ``(h1-h6, p, li)`` and also accounts for 
	 * potential closing inline elements ``a, em, strong, span, b, i``
	 * 
	 * Empty HTMLs shouldn't error
	 *
	 * @param   $text Text to transform
	 *
	 * @return  The string with widows (hopefully) eliminated
	 */
	function widont ($text) {
		$tags = "a|span|i|b|em|strong|acronym|caps|sub|sup|abbr|big|small|code|cite|tt";
		
	    // This regex is a beast, tread lightly
	    $widontFinder = "/([^\s])\s+(((<($tags)[^>]*>)*\s*[^\s<>]+)(<\/($tags)>)*[^\s<>]*\s*(<\/(p|h[1-6]|li)>|$))/i";

	    return preg_replace($widontFinder, '$1&nbsp;$2', $text);
	}
	
	/**
	 * Puts a &thinsp; before and after an &ndash or &mdash;
	 * Dashes may have whitespace or an ``&nbsp;`` on both sides
	 *
	 * @param   $text Text to transform
	 *
	 * @return  The string with dashes padded with &thinsp;
	 */
	function dash ($text) {
	    $dashFinder = "/(\s|&nbsp;|&thinsp;)*(&mdash;|&ndash;|&#x2013;|&#8211;|&#x2014;|&#8212;)(\s|&nbsp;|&thinsp;)*/";
	    return preg_replace($dashFinder, '&thinsp;\\2&thinsp;', $text);
	}
	
	
	/**
	 * Wraps multiple capital letters in ``<span class="caps">`` 
	 * so they can be styled with CSS. 
	 * 
	 * Uses the smartypants tokenizer to not screw with HTML or with tags it shouldn't.
	 *
	 * @param   $text Text to transform
	 *
	 * @return  The string with caps wrapped
	 */
	function caps ($text) {
	    $tokens = $this->TokenizeHTML($text);    
	    $result = array();
	    $inSkippedTag = false;

	    $capFinder = "/(
	            (\b[A-Z\d]*        # Group 2: Any amount of caps and digits
	            [A-Z]\d*[A-Z]      # A cap string much at least include two caps (but they can have digits between them)
	            [A-Z\d]*\b)        # Any amount of caps and digits
	            | (\b[A-Z]+\.\s?   # OR: Group 3: Some caps, followed by a '.' and an optional space
	            (?:[A-Z]+\.\s?)+)  # Followed by the same thing at least once more
	            (?:\s|\b|$))/x";

	    $tagsToSkipRegex = "/<(\/)?(?:pre|code|kbd|script|math)[^>]*>/i";

	    foreach ($tokens as $token) {
	        if ( $token[0] == "tag" ) {
	            // Don't mess with tags.
	            $result[] = $token[1];
	            $closeMatch = preg_match($tagsToSkipRegex, $token[1]);            
				
	            if ($closeMatch) {
	                $inSkippedTag = true;
	            }
	            else {
	                $inSkippedTag = false;
	            }
	        }
	        else {
	            if ($inSkippedTag) {
	                $result[] = $token[1];
	            }
	            else {
	                $result[] = preg_replace_callback($capFinder, array('typogrifyhelper', '__capWrapper'), $token[1]);
	            }
	        }
	    }
		
	    return implode("", $result);    
	}
	
	/**
	 * This is necessary to keep dotted cap strings to pick up extra spaces
	 * used in preg_replace_callback in caps()
	 *
	 * @param   $matchObj The function that called this one
	 *
	 * @return  A formatted string
	 */
	function __capWrapper ($matchObj) {
	    if (!empty($matchObj[2])) {
	        return sprintf('<span class="caps">%s</span>', $matchObj[2]);
	    }
	    else {
	        $mThree = $matchObj[3];
	        if (($mThree{strlen($mThree)-1}) == " ") {
	            $caps = substr($mThree, 0, -1);
	            $tail = ' ';
	        }
	        else {
	            $caps = $mThree;
	            $tail = '';
	        }            
	        return sprintf('<span class="caps">%s</span>%s', $caps, $tail);
	    }
	}
	
	/**
	 * initial_quotes
	 *
	 * Wraps initial quotes in ``class="dquo"`` for double quotes or  
	 * ``class="quo"`` for single quotes. Works in these block tags ``(h1-h6, p, li)``
	 * and also accounts for potential opening inline elements ``a, em, strong, span, b, i``
	 * Optionally choose to apply quote span tags to Gullemets as well.
	 *
	 * @param   $text The string to format initial quotes
	 * @param   $doGuillemets Also do << and >>?
	 *
	 * @return  The text string with initial quotes wrapped with class="dquo" or class="quo"
	 */
	function initial_quotes ($text, $doGuillemets = false) {
		/*
		- start with an opening p, h1-6, li or the start of the string
		- optional white space
		- optional opening inline tags, with more optional white space for each.
		- Find me a quote! (only need to find the left quotes and the primes)
		- double quotes are in group 7, singles in group 8
		*/
		
	    $quoteFinder = "/((<(p|h[1-6]|li)[^>]*>|^)\s*(<(a|em|span|strong|i|b)[^>]*>\s*)*)((\"|&ldquo;|&\#8220;)|('|&lsquo;|&\#8216;))/ix";

	    if ($doGuillemets) {
			/*
			- start with an opening p, h1-6, li or the start of the string
			- optional white space!
			- optional opening inline tags, with more optional white space for each.
			- Find me a quote! (only need to find the left quotes and the primes) - also look for guillemets (>> and << characters))
			- double quotes are in group 7, singles in group 8
			*/
	    	$quoteFinder = "/((<(p|h[1-6]|li)[^>]*>|^)\s*(<(a|em|span|strong|i|b)[^>]*>\s*)*)((\"|&ldquo;|&\#8220;|\xAE|&\#171;|&laquo;)|('|&lsquo;|&\#8216;))/ix";
	    }

	    return preg_replace_callback($quoteFinder, array('typogrifyhelper', '__quoteWrapper'), $text);
	}
	
	/**
	 * This is necessary to keep quote string formatted properly
	 *
	 * @param   $matchObj The function that called this one
	 *
	 * @return  A formatted string
	 */
	function __quoteWrapper ($matchObj) {
	    if ( !empty($matchObj[7]) ) {
	        $classname = "dquo";
	        $quote = $matchObj[7];
	    }
	    else {
	        $classname = "quo";
	        $quote = $matchObj[8];
	    }
	    return sprintf('%s<span class="%s">%s</span>', $matchObj[1], $classname, $quote);
	}

	/**
	 * The main SmartyPants function. Calls the other formatters
	 *
	 * @param   $text The text to format
	 * @param   $attr (Optional) Overridden attribute setting, for applying different formatting
	 *
	 * @return  The formatted text, looking pretty
	 */
	function smartyPants($text, $attr = NULL) {
		if ($attr == NULL) {
			$attr = $this->smartypantsAttr;
		}

		// Options to specify which transformations to make:
		$doStupefy = FALSE;
		$convertQuot = 0;  # should we translate &quot; entities into normal quotes?

		# Parse attributes:
		# 0 : do nothing
		# 1 : set all
		# 2 : set all, using old school en- and em- dash shortcuts
		# 3 : set all, using inverted old school en and em- dash shortcuts
		# 
		# q : quotes
		# b : backtick quotes (``double'' only)
		# B : backtick quotes (``double'' and `single')
		# d : dashes
		# D : old school dashes
		# i : inverted old school dashes
		# e : ellipses
		# w : convert &quot; entities to " for Dreamweaver users

		if ($attr == "0") {
			# Do nothing.
			return $text;
		}
		else if ($attr == "1") {
			# Do everything, turn all options on.
			$doQuotes    = 1;
			$doBackticks = 1;
			$doDashes    = 1;
			$doEllipses  = 1;
		}
		else if ($attr == "2") {
			# Do everything, turn all options on, use old school dash shorthand.
			$doQuotes    = 1;
			$doBackticks = 1;
			$doDashes    = 2;
			$doEllipses  = 1;
		}
		else if ($attr == "3") {
			# Do everything, turn all options on, use inverted old school dash shorthand.
			$doQuotes    = 1;
			$doBackticks = 1;
			$doDashes    = 3;
			$doEllipses  = 1;
		}
		else if ($attr == "-1") {
			# Special "stupefy" mode.
			$doStupefy   = 1;
		}
		else {
			$chars = preg_split('//', $attr);
			foreach ($chars as $c){
				if ($c == "q") {
					$doQuotes = 1;
				}
				elseif ($c == "b") {
					$doBackticks = 1;
				}
				elseif ($c == "B") {
					$doBackticks = 2;
				}
				elseif ($c == "d") {
					$doDashes = 1;
				}
				elseif ($c == "D") {
					$doDashes = 2;
				}
				elseif ($c == "i") {
					$doDashes= 3;
				}
				elseif ($c == "e") {
					$doEllipses = 1;
				}
				elseif ($c == "w") {
					$convertQuot = 1;
				}
				else {
					# Unknown attribute option, ignore.
				}
			}
		}

		$tokens = $this->TokenizeHTML($text);
		$result = '';
		$inPre = 0;  // Keep track of when we're inside <pre> or <code> tags.

		/* $prevTokenLastChar is a cheat, used to get some context
		   for one-character tokens that consist of 
		   just a quote char. What we do is remember
		   the last character of the previous text
		   token, to use as context to curl single-
		   character quote tokens correctly. */
		$prevTokenLastChar = "";

		foreach ($tokens as $currentToken) {
			if ($currentToken[0] == "tag") {
				// Don't mess with quotes inside tags.
				$result .= $currentToken[1];
				if (preg_match("@$this->spTagsToSkip@", $currentToken[1], $matches)) {
					$inPre = isset($matches[1]) && $matches[1] == '/' ? 0 : 1;
				}
			}
			else {
				$t = $currentToken[1];
				$lastChar = substr($t, -1); // Remember last char of this token before processing.
				if (! $inPre) {
					$t = $this->processEscapes($t);

					if ($convertQuot) {
						$t = preg_replace('/&quot;/', '"', $t);
					}

					if ($doDashes) {
						if ($doDashes == 1) {
							$t = $this->educateDashes($t);
						}
						if ($doDashes == 2) {
							$t = $this->educateDashesOldSchool($t);
						}
						if ($doDashes == 3) {
							$t = $this->educateDashesOldSchoolInverted($t);
						}
					}

					if ($doEllipses) $t = $this->educateEllipses($t);

					// Note: backticks need to be processed before quotes.
					if ($doBackticks) {
						$t = $this->educateBackticks($t);
						if ($doBackticks == 2) $t = $this->educateSingleBackticks($t);
					}

					if ($doQuotes) {
						if ($t == "'") {
							# Special case: single-character ' token
							if (preg_match('/\S/', $prevTokenLastChar)) {
								$t = "&#8217;";
							}
							else {
								$t = "&#8216;";
							}
						}
						elseif ($t == '"') {
							# Special case: single-character " token
							if (preg_match('/\S/', $prevTokenLastChar)) {
								$t = "&#8221;";
							}
							else {
								$t = "&#8220;";
							}
						}
						else {
							# Normal case:
							$t = $this->educateQuotes($t);
						}
					}

					if ($doStupefy) {
						$t = $this->stupefyEntities($t);
					}
				}
				$prevTokenLastChar = $lastChar;
				$result .= $t;
			}
		}

		return $result;
	}

	/**
	 * SmartQuotes function. Unused?
	 *
	 * @param   $text Text to parse
	 * @param   $attr Attribute processing flag
	 * 
	 * @return  Processed text
	 */
	function smartQuotes($text, $attr = NULL) {
		if ($attr == NULL) {
			$attr = $this->smartypantsAttr;
		}

		$doBackticks; // should we educate ``backticks'' -style quotes?

		if ($attr == 0) {
			// do nothing;
			return $text;
		}
		else if ($attr == 2) {
			// smarten ``backticks'' -style quotes
			$doBackticks = 1;
		}
		else {
			$doBackticks = 0;
		}

		/* Special case to handle quotes at the very end of $text when preceded by
		   an HTML tag. Add a space to give the quote education algorithm a bit of
		   context, so that it can guess correctly that it's a closing quote: */
		$addExtraSpace = 0;
		if (preg_match("/>['\"]\\z/", $text)) {
			$addExtraSpace = 1; # Remember, so we can trim the extra space later.
			$text .= " ";
		}

		$tokens = $this->TokenizeHTML($text);
		$result = '';
		$inPre = 0;  # Keep track of when we're inside <pre> or <code> tags

		/* $prevTokenLastChar is a cheat, used to get some context
		   for one-character tokens that consist of 
		   just a quote char. What we do is remember
		   the last character of the previous text
		   token, to use as context to curl single-
		   character quote tokens correctly. */
		$prevTokenLastChar = "";

		foreach ($tokens as $currentToken) {
			if ($currentToken[0] == "tag") {
				# Don't mess with quotes inside tags
				$result .= $currentToken[1];
				if (preg_match("@$this->spTagsToSkip@", $currentToken[1], $matches)) {
					$inPre = isset($matches[1]) && $matches[1] == '/' ? 0 : 1;
				}
			}
			else {
				$t = $currentToken[1];
				$lastChar = substr($t, -1); // Remember last char of this token before processing.
				if (! $inPre) {
					$t = $this->processEscapes($t);
					if ($doBackticks) {
						$t = $this->educateBackticks($t);
					}

					if ($t == "'") {
						// Special case: single-character ' token
						if (preg_match('/\S/', $prevTokenLastChar)) {
							$t = "&#8217;";
						}
						else {
							$t = "&#8216;";
						}
					}
					elseif ($t == '"') {
						// Special case: single-character " token
						if (preg_match('/\S/', $prevTokenLastChar)) {
							$t = "&#8221;";
						}
						else {
							$t = "&#8220;";
						}
					}
					else {
						# Normal case:
						$t = $this->educateQuotes($t);
					}

				}
				$prevTokenLastChar = $lastChar;
				$result .= $t;
			}
		}

		if ($addExtraSpace) {
			preg_replace('/ \z/', '', $result);  # Trim trailing space if we added one earlier.
		}
		return $result;
	}

	/**
	 * Replaces dashes with proper em and en dashes. Unused?
	 * 
	 * @param   $text The text to parse
	 * @param   $attr The flag to what kind of processing we're doing.
	 *
	 * @return  The processed text
	 */
	function smartDashes($text, $attr = NULL) {
		if ($attr == NULL) {
			$attr = $this->smartypantsAttr;
		}

		# reference to the subroutine to use for dash education, default to educateDashes:
		$dashSubRef = 'educateDashes';

		if ($attr == 0) {
			# do nothing;
			return $text;
		}
		else if ($attr == 2) {
			# use old smart dash shortcuts, "--" for en, "---" for em
			$dashSubRef = 'educateDashesOldSchool'; 
		}
		else if ($attr == 3) {
			# inverse of 2, "--" for em, "---" for en
			$dashSubRef = 'educateDashesOldSchoolInverted'; 
		}

		$tokens;
		$tokens = $this->TokenizeHTML($text);

		$result = '';
		$inPre = 0;  # Keep track of when we're inside <pre> or <code> tags
		foreach ($tokens as $currentToken) {
			if ($currentToken[0] == "tag") {
				# Don't mess with quotes inside tags
				$result .= $currentToken[1];
				if (preg_match("@$this->spTagsToSkip@", $currentToken[1], $matches)) {
					$inPre = isset($matches[1]) && $matches[1] == '/' ? 0 : 1;
				}
			} else {
				$t = $currentToken[1];
				if (! $inPre) {
					$t = $this->processEscapes($t);
					$t = $dashSubRef($t);
				}
				$result .= $t;
			}
		}
		return $result;
	}

	/**
	 * Replaces ... or . . . with proper &hellip; Unused?
	 * 
	 * @param   $text The text to parse
	 * @param   $attr The flag to what kind of processing we're doing.
	 *
	 * @return  The processed text
	 */
	function smartEllipses($text, $attr = NULL) {
		if ($attr == NULL) {
			$attr = $this->smartypantsAttr;
		}

		if ($attr == 0) {
			# do nothing;
			return $text;
		}
		
		$tokens = $this->TokenizeHTML($text);

		$result = '';
		$inPre = 0;  # Keep track of when we're inside <pre> or <code> tags
		foreach ($tokens as $currentToken) {
			if ($currentToken[0] == "tag") {
				# Don't mess with quotes inside tags
				$result .= $currentToken[1];
				if (preg_match("@$this->spTagsToSkip@", $currentToken[1], $matches)) {
					$inPre = isset($matches[1]) && $matches[1] == '/' ? 0 : 1;
				}
			} else {
				$t = $currentToken[1];
				if (! $inPre) {
					$t = $this->processEscapes($t);
					$t = $this->educateEllipses($t);
				}
				$result .= $t;
			}
		}
		return $result;
	}

	/**
	 * Process quotes into HTML entities
	 *
	 * Example input:  "Isn't this fun?"
	 * Example output: &#8220;Isn&#8217;t this fun?&#8221;	
	 *
	 * @param   $str String to process
	 *
	 * @return  The string, with "educated" curly quote HTML entities.
	 */
	function educateQuotes($str) {

		# Make our own "punctuation" character class, because the POSIX-style
		# [:PUNCT:] is only available in Perl 5.6 or later:
		$punctuationClass = "[!\"#\\$\\%'()*+,-.\\/:;<=>?\\@\\[\\\\\]\\^_`{|}~]";

		# Special case if the very first character is a quote
		# followed by punctuation at a non-word-break. Close the quotes by brute force:
		$str = preg_replace(
			array("/^'(?=$punctuationClass\\B)/", "/^\"(?=$punctuationClass\\B)/"),
			array('&#8217;',                 '&#8221;'), $str);


		# Special case for double sets of quotes, e.g.:
		#   <p>He said, "'Quoted' words in a larger quote."</p>
		$str = preg_replace(
			array("/\"'(?=\w)/",    "/'\"(?=\w)/"),
			array('&#8220;&#8216;', '&#8216;&#8220;'), $str);

		# Special case for decade abbreviations (the '80s):
		$str = preg_replace("/'(?=\\d{2}s)/", '&#8217;', $str);

		$closeClass = '[^\ \t\r\n\[\{\(\-]';
		$decDashes = '&\#8211;|&\#8212;';

		// Get most opening single quotes:
		/*
		(
			a whitespace char, or
			anon-breaking space entity, or
			dashes, or
			named dash entities
			or decimal entities
			or hex
		)
		the quote
		followed by a word character
		*/
		$str = preg_replace("{(\\s|&nbsp;|--|&[mn]dash;|$decDashes|&\\#x201[34];)'(?=\\w)}x", '\1&#8216;', $str);
		
		// Single closing quotes:
		/*
		If $1 captured, then do nothing;
		otherwise, positive lookahead for a whitespace
		char or an 's' at a word ending position. This
		is a special case to handle something like:
		\"<i>Custer</i>'s Last Stand.\"
		*/
		$str = preg_replace("{($closeClass)?'(?(1)|(?=\\s | s\\b))}xi", '\1&#8217;', $str);

		// Any remaining single quotes should be opening ones:
		$str = str_replace("'", '&#8216;', $str);

		/* Get most opening double quotes:
		a whitespace char, or
		a non-breaking space entity, or
		dashes, or
		named dash entities
		or decimal entities
		or hex
		
		the quote
		followed by a word character
		*/
		$str = preg_replace("{(\\s|&nbsp;|--|&[mn]dash;|$decDashes|&\\#x201[34];)\"(?=\\w)}x", '\1&#8220;', $str);

		/* Double closing quotes:
		
		If $1 captured, then do nothing;
		if not, then make sure the next char is whitespace.
		*/
		$str = preg_replace("{($closeClass)?\"(?(1)|(?=\\s))}x", '\1&#8221;', $str);

		//Any remaining quotes should be opening ones.
		$str = str_replace('"', '&#8220;', $str);

		return $str;
	}

	/**
	 * Process backticks-style doublequotes translated into proper HTML entities
	 *   Example input:  ``Isn't this fun?''
	 *   Example output: &#8220;Isn't this fun?&#8221;
	 *
	 * @param $str The string to convert backticks in
	 *
	 * @return The processed string
	 */
	function educateBackticks($str) {

		$str = str_replace(array("``",       "''",),
						 array('&#8220;', '&#8221;'), $str);
		return $str;
	}

	/**
	 * Formats string with `backticks' -style single quotes
	 * translated into HTML curly quote entities.
	 * Example input:  `Isn't this fun?'
	 * Example output: &#8216;Isn&#8217;t this fun?&#8217;
	 * 	
	 * @param $str The string to process
	 *	
	 * @return The processed string
	 */
	function educateSingleBackticks($str) {
		$str = str_replace(array("`",       "'",),
						 array('&#8216;', '&#8217;'), $str);
		return $str;
	}

	/**
	 * Processes a string with each instance of "--" translated to
	 * an em-dash HTML entity.
	 * 
	 * @param $str The strong to process
	 * 
	 * @return The processed string
	 *
	 */
	function educateDashes($str) {
		$str = str_replace('--', '&#8212;', $str);
		return $str;
	}

	/**
	 * Processes a string with each instance of "--" translated to
	 * an en-dash HTML entity, and each "---" translated to
	 * an em-dash HTML entity.
	 *
	 * @param $str The string to process
	 *
	 * @return The processed string
	 */
	function educateDashesOldSchool($str) {
		//                     em         en
		$str = str_replace(array("---",     "--",),
						 array('&#8212;', '&#8211;'), $str);
		return $str;
	}

	/**
	 * Processes a string, with each instance of "--" translated to
	 * an em-dash HTML entity, and each "---" translated to
	 * an en-dash HTML entity. Two reasons why: First, unlike the
	 * en- and em-dash syntax supported by
	 * educateDashesOldSchool(), it's compatible with existing
	 * entries written before SmartyPants 1.1, back when "--" was
	 * only used for em-dashes.  Second, em-dashes are more
	 * common than en-dashes, and so it sort of makes sense that
	 * the shortcut should be shorter to type. (Thanks to Aaron
	 * Swartz for the idea.)
	 *
	 * @param $str The string to process
	 *
	 * @return The processed string
	 */
	function educateDashesOldSchoolInverted($str) {
	    //                  	en         em
		$str = str_replace(array("---",     "--",),
						 array('&#8211;', '&#8212;'), $str);
		return $str;
	}

	/**
	 * Processes a string with each instance of "..." translated to
	 * an ellipsis HTML entity. Also converts the case where
	 * there are spaces between the dots.
	 *
	 * Example input:  Huh...?
	 * Example output: Huh&#8230;?
	 * 
	 * @param $str The string to process
	 *
	 * @return The processed string
	 */
	function educateEllipses($str) {
		$str = str_replace(array("...",     ". . .",), '&#8230;', $str);
		return $str;
	}

	/**
	 * Processes a string, with each SmartyPants HTML entity translated to
	 * its ASCII counterpart.
	 *
	 * Example input:  &#8220;Hello &#8212; world.&#8221;
	 * Example output: "Hello -- world."
	 *
	 * @param $str The string to process
	 *
	 * @return The processed string
	 */
	function stupefyEntities($str) {
							//  en-dash    em-dash
		$str = str_replace(array('&#8211;', '&#8212;'),
						 array('-',       '--'), $str);

		// single quote         open       close
		$str = str_replace(array('&#8216;', '&#8217;'), "'", $str);

		// double quote         open       close
		$str = str_replace(array('&#8220;', '&#8221;'), '"', $str);

		$str = str_replace('&#8230;', '...', $str); // ellipsis

		return $str;
	}

	/**
	 * Processes a string, with after processing the following backslash
	 * escape sequences. This is useful if you want to force a "dumb"
	 * quote or other character to appear.
	 *
	 * Escape  Value
	 * ------  -----
	 * \\      &#92;
	 * \"      &#34;
	 * \'      &#39;
	 * \.      &#46;
	 * \-      &#45;
	 * \`      &#96;
	 * 
	 * @param $str The string to process.
	 *
	 * @return The processed string
	 */
	function processEscapes($str) {
		$str = str_replace(
			array('\\\\',  '\"',    "\'",    '\.',    '\-',    '\`'),
			array('&#92;', '&#34;', '&#39;', '&#46;', '&#45;', '&#96;'), $str);

		return $str;
	}

	/**
	 * Processes a string, and transforms it into an array of the tokens comprising the input
	 * string. Each token is either a tag (possibly with nested,
	 * tags contained therein, such as <a href="<MTFoo>">, or a
	 * run of text between tags. Each element of the array is a
	 * two-element array; the first is either 'tag' or 'text';
	 * the second is the actual value.
	 *
	 * Regular expression derived from the _tokenize() subroutine in 
	 * Brad Choate's MTRegex plugin.
	 * <http://www.bradchoate.com/past/mtregex.php>
	 * 
	 * @param $str String containing HTML markup.
	 * 
	 * @return The tokenized array
	 */
	function TokenizeHTML($str) {
		$index = 0;
		$tokens = array();

		/*
		comment
		processing instruction
		regular tags
		*/
		$match = '(?s:<!(?:--.*?--\s*)+>)|'.	
				 '(?s:<\?.*?\?>)|'.
				 '(?:<[/!$]?[-a-zA-Z0-9:]+\b(?>[^"\'>]+|"[^"]*"|\'[^\']*\')*>)'; 

		$parts = preg_split("{($match)}", $str, -1, PREG_SPLIT_DELIM_CAPTURE);

		foreach ($parts as $part) {
			if (++$index % 2 && $part != '') {
				$tokens[] = array('text', $part);
			}
			else {
				$tokens[] = array('tag', $part);
			}
		}
		return $tokens;
	}
}

/*
Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:

	*	Redistributions of source code must retain the above copyright notice, 
		this list of conditions and the following disclaimer.

	* 	Redistributions in binary form must reproduce the above copyright 
		notice, this list of conditions and the following disclaimer in the 
		documentation and/or other materials provided with the distribution.

	*	Neither the name of the php-typogrify nor the names of its contributors
		may be used to endorse or promote products derived from this software 
		without specific prior written permission.

	*	Neither the name "SmartyPants" nor the names of its contributors may
		be used to endorse or promote products derived from this software
		without specific prior written permission.

	*	Neither the name "CakePHP TypogrifyHelper" nor the names of its contributors 
		may be used to endorse or promote products derived from this software without
		specific prior written permission.

		THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
		"AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
		LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
		A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR
		CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,
		EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
		PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR
		PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF
		LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
		NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
		SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

=============
php-typogrify
=============

Announcement:
<http://www2.jeffcroft.com/sidenotes/2007/may/29/typogrify-easily-produce-web-typography-doesnt-suc/>

Example Page:
<http://static.mintchaos.com/projects/typogrify/>

Project Page:
<http://code.google.com/p/typogrify/>

PHP SmartyPants
<http://www.michelf.com/projects/php-smartypants/>

===============	
PHP SmartyPants
===============
Smart punctuation for web sites

by John Gruber
<http://daringfireball.net>

PHP port by Michel Fortin
<http://www.michelf.com/>

Copyright (c) 2003-2004 John Gruber
Copyright (c) 2004-2005 Michel Fortin

Description
-----------

This is a PHP translation of the original SmartyPants quote educator written in
Perl by John Gruber.

SmartyPants is a web publishing utility that translates plain ASCII
punctuation characters into "smart" typographic punctuation HTML
entities. SmartyPants can perform the following transformations:

*	Straight quotes (`"` and `'`) into "curly" quote HTML entities
*	Backticks-style quotes (` ``like this'' `) into "curly" quote HTML 
	entities
*	Dashes (`--` and `---`) into en- and em-dash entities
*	Three consecutive dots (`...`) into an ellipsis entity

SmartyPants does not modify characters within `<pre>`, `<code>`, `<kbd>`, 
`<script>`, or `<math>` tag blocks. Typically, these tags are used to 
display text where smart quotes and other "smart punctuation" would not 
be appropriate, such as source code or example markup.


### Backslash Escapes ###

If you need to use literal straight quotes (or plain hyphens and
periods), SmartyPants accepts the following backslash escape sequences
to force non-smart punctuation. It does so by transforming the escape
sequence into a decimal-encoded HTML entity:

	Escape  Value  Character
	------  -----  ---------
	  \\    &#92;    \
	  \"    &#34;    "
	  \'    &#39;    '
	  \.    &#46;    .
	  \-    &#45;    -
	  \`    &#96;    `

This is useful, for example, when you want to use straight quotes as
foot and inch marks: 6'2" tall; a 17" iMac.

### Algorithmic Shortcomings ###

One situation in which quotes will get curled the wrong way is when
apostrophes are used at the start of leading contractions. For example:

	'Twas the night before Christmas.

In the case above, SmartyPants will turn the apostrophe into an opening
single-quote, when in fact it should be a closing one. I don't think
this problem can be solved in the general case -- every word processor
I've tried gets this wrong as well. In such cases, it's best to use the
proper HTML entity for closing single-quotes (`&#8217;`) by hand.


Author
------

John Gruber
<http://daringfireball.net/>

Ported to PHP by Michel Fortin
<http://www.michelf.com/>


Additional Credits
------------------

Portions of this plug-in are based on Brad Choate's nifty MTRegex plug-in.
Brad Choate also contributed a few bits of source code to this plug-in.
Brad Choate is a fine hacker indeed. (<http://bradchoate.com/>)

Jeremy Hedley (<http://antipixel.com/>) and Charles Wiltgen
(<http://playbacktime.com/>) deserve mention for exemplary beta testing.

*/
?>