<?php
	if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../../../').'/');
	if(!defined('EPUB_DIR')) define('EPUB_DIR',realpath(dirname(__FILE__).'/../').'/');
	require_once(EPUB_DIR.'/helper.php');
			/**
			utilities
			*/

		function prepare_metadata()
		{
			global $conf;
			$metadata['language'] = $conf['lang'];
			$metadata['creator'] = rawurldecode($_POST['user']);
			$metadata['creator.role'] = 'aut';
			$url=rawurldecode($_POST['location']);
			$url=dirname($url);
            $title=rawurldecode($_POST['title']);
			if ($title) {
				$title_items = explode('|', $title);
				if (count($title_items) > 1) {
					$title = array_shift($title_items);
				}
			}
			$metadata['title'] = $title;
            $unique_identifier = rtrim($url,'/');
            $ident_title =  str_replace(' ', '_',strtolower(trim($title)));
            $unique_identifier .= "/$ident_title";
			//$uniq_id = str_replace('/','', DOKU_BASE) . "_id";
			$metadata['identifier'] = $unique_identifier;
			$metadata['identifier.id'] = "book_id";
			foreach	($title_items as $title_item) {
				$index = strpos($title_item, '=');
				if ($index !== false) {
					$name = trim(substr($title_item, 0, $index));
					if ($name) {
						$value = trim(substr($title_item, $index + 1));
						if ($value) {
							$metadata[$name] = $value;
						}
					}
				}
			}
            $dc_date =  date("r");
			$metadata['date'] = $dc_date;
			return $metadata;
		}

		function metadata_text($metadata)
		{
			//echo 'Metadata: '.print_r($metadata, true);
			$dc = '';
			foreach ($metadata as $name => $value) {
				$is_attribute = strpos($name, '.') > 0;
				if ($is_attribute)
					continue;
				// node
				$dc .= '<dc:'.$name;
				$prefix = $name.'.';
				$attribute_names = array_filter(array_keys($metadata), function($i) use($prefix) { return strpos($i, $prefix) === 0; });
				//echo $name.' attributes: '.print_r($attribute_names, true);
				foreach	($attribute_names as $attribute_name) {
					$attribute_name = substr($attribute_name, strlen($prefix));
					$attribute_value = $metadata[$name . '.' . $attribute_name];
					$dc .= " $attribute_name=\"$attribute_value\"";
				}
				$dc .= ">$value</dc:$name>\n";
			}
			return $dc;
		}

	  /*
	      Creates headers for both the .opf and .ncx files
      */
		function epub_opf_header($user_title, $skip_default_title)
		{
			$metadata = prepare_metadata();
			$metadata_text = metadata_text($metadata);
            $epub_version = "fckglite";
            $info_data = file(EPUB_DIR . 'plugin.info.txt',FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            for($i=0; $i<count($info_data); $i++) {
            if(strpos($info_data[$i],'date') !== false) {
                $epub_version = trim(str_replace('date',"",$info_data[$i]));
                break;
               }
            }
			if ($user_title || !$skip_default_title)
				$title_html = '<item id="cover" href="Text/title.html" media-type="application/xhtml+xml"/>'."\n";
            if(!$user_title && !$skip_default_title) {
               $cover_png='<item id="cover-image" href="Images/cover.png" media-type="image/png"/>'."\n";
            }
			$uniq_id = $metadata['identifier.id'];
			$outp = <<<OUTP
<?xml version='1.0' encoding='utf-8'?>
<package xmlns="http://www.idpf.org/2007/opf" xmlns:dc="http://purl.org/dc/elements/1.1/"
   unique-identifier="$uniq_id" version="2.0">
<metadata>
$metadata_text
<meta name="cover" content="cover-image" />
<meta name="plugin version" content="$epub_version"/>
</metadata>
<manifest>
<item id="ncx" href="toc.ncx" media-type="application/x-dtbncx+xml"/>
 $title_html
 $cover_png
OUTP;

			$dir =  epub_get_metadirectory() .  'OEBPS/';
			io_saveFile($dir . 'content.opf',$outp);

			flush();

		$title = $metadata['title'];
		$ncx=<<<NCX
<?xml version="1.0"?>
<!DOCTYPE ncx PUBLIC "-//NISO//DTD ncx 2005-1//EN"
 "http://www.daisy.org/z3986/2005/ncx-2005-1.dtd">
 <ncx version="2005-1" xml:lang="en" xmlns="http://www.daisy.org/z3986/2005/ncx/">
  <head>
     <meta content="$url" name="dtb:uid"/>
    <meta content="1" name="dtb:depth"/>
    <meta content="0" name="dtb:totalPageCount"/>
    <meta content="0" name="dtb:maxPageNumber"/>
  </head>
   <docTitle>
    <text>$title</text>
  </docTitle>
  <navMap>

NCX;
          io_saveFile($dir . 'toc.ncx',$ncx);

		}

		function epbub_entity_replace($matches) {
		global $entities;

		if(array_key_exists($matches[0], $entities)) {
		return $entities[$matches[0]];
		}
		return $matches[0];
		}

		function epub_css($plugin) {
            $use_less = false;
            if($plugin !== false) {
               $r = $plugin->get_renderer();
               $use_less = $r->getConf('less');
            }

            $css = 	(class_exists('lessc') && $use_less) ? 'css3.php' : 'css2.php';
            if($css ==  'css3.php') {
                echo "Using LESS: $css\n";
            }
            else {
                echo   "Not using LESS, either not found or excluded by configuration: using $css\n";
            }
		    require_once($css);
		    epub_css_out(epub_get_oebps());
		}

	    function epub_write_spine($use_title) {
		    $items = epub_push_spine();
			epub_opf_write('<spine toc="ncx">');
			if ($use_title)
				epub_opf_write('<itemref idref="cover" linear="no"/>');
			foreach($items as $page) {
	            epub_opf_write('<itemref idref="' . $page[1] . '" linear="yes"/>');
			}
			epub_opf_write('</spine>');
		}

		function epub_write_footer($use_title) {
			if ($use_title) {
				$footer=<<<FOOTER
  <guide>
    <reference href="Text/title.html" type="cover" title="Cover"/>
  </guide>
</package>
FOOTER;
			}
		else {
			$footer=<<<FOOTER
</package>
FOOTER;
		}
	     epub_opf_write($footer);
		 $handle = epub_opf_write(null);
		 fclose($handle);
	   }

	   /**
	       returns true if a page id is included among pages being processed for this ebook,
		   otherwise false
		   If a page id is included in the ebook then the Renderer will create a link to it for accesss
		   from within the ebook, otherwise it will create a text version of the url
	   */
		function is_epub_pageid($id) {
		    static $ep_ids;
			if(!$ep_ids)  {
			    if(isset($_POST['epub_ids'])) {
				    $ep_ids =explode(';;',rawurldecode($_POST['epub_ids']));
					$ns_pages = epub_save_ns_pages();
                    if($ns_pages) {
                        $ep_ids = array_merge($ns_pages,$ep_ids);
                    }
                      //echo print_r($ep_ids,true);
				}
				else {
				    return false;
				}
			}

			if(in_array($id,$ep_ids)) return true;

			return in_array(":$id",$ep_ids);
		}

        function epub_get_metadirectory($temp_user=null) {
	        static $dir;

			$seed = md5(rawurldecode($_POST['user']).time());
		    if(!$dir) {
                  if(isset($_POST['client'])) {
				      $user= rawurldecode($_POST['client']) . ":$seed";
				  }
			      else {
					 $user=$temp_user?"$temp_user:$seed":$seed;
				  }
				  $dir = dirname(metaFN("epub:$user:tmp",'.meta')) . '/';
		    }

		    return $dir;
	    }


	   function epub_get_data_media() {
	         static $dir;
             global $conf;
			 if(!$dir) {
			     $dir = init_path($conf['savedir']) . '/media/';
			 }

			 return $dir;

	   }

	     /*
		    returns full path to the OEPBS directory
		 */
   	   function epub_get_oebps() {
	         static $dir;
			 if(!$dir) {
			      $dir=epub_get_metadirectory() . 'OEBPS/';
				 }
			 return $dir;

	   }

	     /**
		    maintains the item id
		 */
	    function epub_itemid() {
		  static $num = 0;
		     return 'item' . ++$num;
		}

        function epub_fn() {
		    static $num = 0;
		    return ++$num;
		}
       function epub_close_footnotes() {
	         $handle = epub_footnote_handle(true);
			 if(!$handle) return;
		     $item_num=epub_write_item('Text/footnotes.html', "application/xhtml+xml");
			 epub_push_spine(array('Text/footnotes.html',$item_num, 1));
			 fwrite($handle,"\n</div></body></html>");
	   }

        function epub_write_footnote($fn_id,$page,$url) {
            static $handle;
			static $current_page="";
			if(!$handle)  {
			    $handle = epub_footnote_handle();
				epub_write_fn_header($handle);
			}
			if($current_page != $page) {
			fwrite($handle,"<br/><h1><a name='$page' id='$page'>$page</a></h1>\n");
			}

            $url = preg_replace("/\s*\(.*?\)\s*$/","",$url);


            if(!preg_match("#https?#",$url)) {
                $url = preg_replace('#' . DOKU_BASE . '#',  DOKU_URL, $url);
            }

			$footnote = "<a href='$page#backto_$fn_id' class='wikilink1' title='$page'>[$fn_id]</a> <a href='$url'>$url</a><br />\n";
			fwrite($handle,$footnote);
			$current_page=$page;
		}

		function epub_write_fn_header($handle) {
$header=<<<HEADER
<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN"
"http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<link rel="stylesheet"  type="text/css" href="../Styles/style.css"/>

<title>Footnotes</title></head><body>
<div class='dokuwiki'>
HEADER;
                fwrite($handle,$header);
		}

		function epub_footnote_handle($return_only=false) {
		    static $handle;
			if($return_only) return $handle;
			if(!$handle) {
			    $oebps = epub_get_oebps();
				$handle=fopen($oebps. 'Text/footnotes.html', 'a');
			}
			return $handle;
		}

        function epub_save_image($img,$fullpath){
          $command = "wget --quiet $img -O $fullpath";
          $s = exec ($command, $output,$retv);

           if(!$retv && file_exists($fullpath)) {
               return true;
           }
           else echo "Cannot fetch $img\n";

          return false;
        }

	    function epub_write_item($url,$mime_type) {
		   $item_num = epub_itemid() ;
           if(strpos($url, 'cover.png') !== false)  {
               $item='<item  href="' . $url .'" id="cover-image" media-type="' . $mime_type . '" />';
           }
		    else {
              $item='<item href="' . $url .'" id="' . $item_num  . '" media-type="' . $mime_type . '" />';
            }
		    epub_opf_write($item);
			epub_write_zip($url);
			return $item_num;
		}

		 function epub_zip_handle($path=null) {
		    static $zip;
			if(!class_exists ('ZipArchive'))  return false;
			if($path && !$zip) {
			       $zip = new ZipArchive;
                   $zip->open($path);
		    }

			return $zip;
		 }

		 function epub_write_zip($url) {
		     static $zip;
			 static $oebps;

			 if(!$zip)  {
			    $zip = epub_zip_handle();
				if($zip) 	$oebps = epub_get_oebps();
			}
			 if($zip) {
			      $file = $oebps . $url;
			      $zip->addFile($file, "OEBPS/$url");
			 }

		 }

		 /**
		    Adds content.opf and toc.ncx to zip file and closes it
		 */
	    function epub_finalize_zip() {
	        $zip = epub_zip_handle() ;
		    if(!$zip)  return false;
            epub_write_zip('content.opf');
            epub_write_zip('toc.ncx');
		    $zip->close();
		    return true;
        }

		/**
		    loads spine array and adds nav points to the ncx file
			then outputs closing tags to ncx file.  The header to
			ncx file is created in epub_opf_header()
		*/
		function epub_write_ncx($use_title) {
		    $toc  = epub_get_oebps()  . 'toc.ncx';

	        $opf_handle= fopen($toc, 'a');
		    if(!$opf_handle) {
		        echo "unable to open file: $toc\n";
		        exit;
	        }
		    $items = epub_push_spine();

			if ($use_title)
				array_unshift($items,array('Text/title.html', '', 1));
            $num = 0;
			$previousLevel = 0;
			foreach($items as $page) {
			    $num++;
				$level = $page[2];
			    $page = $page[0];
                $title=epub_titlesStack();
                if(!$page) continue;
                if($title) {
                    $title = html_entity_decode($title);
                    echo "found $title for $page\n";
                }
				$navpoint = '';
				for ($l = $previousLevel; $l >= $level; $l--)
					$navpoint .= " </navPoint>\n";
				$previousLevel = $level;
				$navpoint .= <<<NAVPOINT
 <navPoint id="np-$num-l$level" playOrder="$num">
  <navLabel>
   <text>$title</text>
  </navLabel>
  <content src="$page"/>
NAVPOINT;
				fwrite($opf_handle,"$navpoint\n");
			}
            $navpoint = '';
            for ($l = 0; $l < $level; $l++)
                $navpoint .= " </navPoint>\n";
		   fwrite($opf_handle," $navpoint</navMap>\n</ncx>\n");
		   fflush($opf_handle);
           fclose($opf_handle);

		}

		/* write data to opf file */
	    function epub_opf_write($data=null) {
		    static $opf_handle;
			static $opf_content;
			if(!$opf_handle) {
			    $opf_content  = epub_get_oebps()  . 'content.opf';
				$opf_handle= fopen($opf_content, 'a');
				if(!$opf_handle) {
				   echo "unable to open file: $opf_content\n";
				   exit;
				 }
			}

		    if($data) {
				if( fwrite($opf_handle,"$data\n") == false) {
					echo "cannot write to $opf_content\n";
					exit;
				}
			}

	        return $opf_handle;
		}

		function epub_titlesStack($titles=null) {
            static $e_titles;
            if(is_array($titles)) {
               $e_titles=$titles;
            }
            elseif(count($e_titles)) {
                return array_shift($e_titles);
            }
            return "";
        }

	    function epub_setup_book_skel($user_title=false) {
		    $dir=epub_get_metadirectory();
		    $meta = $dir . 'META-INF';
		    $oebps = epub_get_oebps();
			$media_dir = epub_get_data_media() . 'epub';
            io_mkdir_p($meta);
			io_mkdir_p($oebps);
            io_mkdir_p($oebps . 'Images/');
            io_mkdir_p($oebps . 'Audio/');
            io_mkdir_p($oebps . 'Video/');
            io_mkdir_p($oebps . 'Text/');
			io_mkdir_p($media_dir);
            io_mkdir_p($oebps . 'Styles/');
		     if(isset($_POST['client'])) {
				  $user= cleanID(rawurldecode($_POST['client'])). '/';
				  io_mkdir_p($media_dir. '/'. $user);
			  }
			$book_id = cleanID(rawurldecode($_POST['book_page']));

			copy(EPUB_DIR . 'scripts/package/my-book.epub', $dir . 'my-book.epub');
			copy(EPUB_DIR . 'scripts/package/container.xml', $dir . 'META-INF/container.xml');
			if(!$user_title) {
			    copy(EPUB_DIR . 'scripts/package/title.html', $oebps . 'Text/title.html');
			    copy(EPUB_DIR . 'scripts/package/cover.png', $oebps . 'Images/cover.png');
			}
		    $zip = epub_zip_handle($dir . 'my-book.epub');
			if($zip) {
			    $zip->addFile(EPUB_DIR . 'scripts/package/container.xml', 'META-INF/container.xml');
				if(!$user_title) {
					$zip->addFile(EPUB_DIR . 'scripts/package/title.html', 'OEBPS/Text/title.html');
					$zip->addFile(EPUB_DIR . 'scripts/package/cover.png', 'OEBPS/Images/cover.png');
				}
			}
		}

		/* Creates array of files required for spine */
        function epub_push_spine($page=null) {
		    static $spine = array();
			if(!$page) return $spine;
			$spine[] = $page;

		}
		function epub_pack_book() {
		    echo "packing epub\n";

		     $user = "";
		     if(isset($_POST['client'])) {
				  $user= rawurldecode($_POST['client']) . '/';
			  }
		    $meta = epub_get_metadirectory() ;
		    echo "$meta\n";
			 if(!epub_zip_handle() && epub_isWindows()) {
                epub_pack_ZipLib($meta);
			 }
			 elseif(!epub_zip_handle()) {
			    chdir($meta);
			    echo rawurlencode("*nix zip command used \n");
			    $cmd =  'zip -Xr9Dq my-book.epub *';
			    exec($cmd,$ar, $ret);
				if($ret > 0) {
				   echo "zip error: exit status=$ret\n";
				   echo "<b>Error codes:</b>\n  4: memory allocation error\n  11-18: unable write to or create file\n  127: zip command not found\n";
                   echo "Trying ZipLib\n";
                   epub_pack_ZipLib($meta);
				}
			}
			else echo "ziparchive used\n";
            $plugin =& plugin_load('syntax','epub');
			$oldname = $meta . 'my-book.epub';
            $rmdir= $plugin->getConf('rmdir');
            $title = prepare_metadata()['title'];
			$epub_file = $title . '_' . date("Y-m-d-H-i-s") . '.epub';
			$newname = mediaFN("epub:$user:$epub_file");
			if(rename ($oldname , $newname )) {
                $epub_id = cleanID("epub:$user:$epub_file");
               	$helper = new  helper_plugin_epub();
                $id = cleanID(rawurldecode($_POST['book_page']));
                echo "ebook id=$id\n";
                // $title=rawurldecode($_POST['title']);
                $helper->addBook($id,$epub_id,$title);
			    echo "New Ebook: $epub_id\n" ;
                $helper->delete_dw_cachefiles($id);
			}

             if($rmdir == 'y') {
                io_rmdir($meta, true);

                // if(epub_isWindows()) {
                //   $meta = str_replace('/','\\',$meta);
                //    $cmd = "RMDIR /s /q $meta";
                // }
                // else {
                //     $cmd ="rm -f -r $meta";
                // }

                // system($cmd,$retval);
                // if($retval)  {
                //    echo "unable to remove dir:<br />&nbsp;&nbsp;$meta<br />&nbsp;&nbsp;error code: $retval\n";
                //  }
                //  else {
                //      echo "$cmd\n";
                //  }
             }

		}

        function epub_pack_ZipLib($meta) {
			    chdir($meta);
				echo	 rawurlencode("Using Dokuwiki's ZipLib Class\n");
				$epub_file = $meta . 'my-book.epub';
				unlink($epub_file);
                $z = new ZipLib;
                $z->add_File('application/epub+zip', 'mimetype', 0);
                $z->Compress('OEBPS','./');
                $z->Compress('META-INF','./');
                $result = $z->get_file();
                file_put_contents($epub_file,$result);
        }

		function epub_is_installed_plugin($which) {
		    static $installed_plugins;
			if(!$installed_plugins) {
		  	    $installed_plugins=plugin_list('syntax');
			}
			 if(in_array($which, $installed_plugins)) return true;
			 return false;
		}

		function epub_check_for_ditaa(&$xhtml,$renderer) {

		    if(strpos($xhtml,"ditaa/img.php") === false)  return;
			$regex = '#<img src=\"(.*?ditaa.*?)\".*\/>#m';

			if(!preg_match_all($regex,$xhtml,$matches,PREG_SET_ORDER)) return;
			$plugin = plugin_load('syntax','ditaa');

			for($i=0; $i<count($matches); $i++ ) {
 		        list($url,$params) = explode('?',$matches[$i][1]);
				// parse the query string
			    $params = explode('&amp;',$params);

			    $data = array();
			    foreach($params as $param) {
			        list($key,$val) = explode('=',$param);
			       $data[$key]=$val;
		        }
                $cache  = $plugin->_imgfile($data);  // get the image address in data/cache
			    if($cache) {
			        $name=$renderer->copy_media($cache,true);		//copy the image to epub's OEBPS directory and enter it into opf file
				    if($name) {
				     	$regex = '#<img src=\"(.*?' . $data['md5'] . '.*?)\".*\/>#m';	// use the ditaa  plugin's md5 identifier to replace correct image
					    $replace = '<img src="../' . $name . '" />';
					    $xhtml = preg_replace($regex,$replace,$xhtml);
				    }
			    }

			}
		}

		function epub_check_for_graphviz(&$xhtml,$renderer,$data,$graphviz) {
              $cache  = $graphviz->_imgfile($data);
              if($cache) {
                 $name=$renderer->copy_media($cache,true);		//copy the image to epub's OEBPS directory and enter it into opf file
                 $regex = $regex = 'src=.*?'.$data['md5'] . '"' ;
                 $xhtml = preg_replace('#' . $regex .'#',"src=\"../$name\"",$xhtml);
            }
       }

		function epub_check_for_math(&$xhtml,$renderer) {
		    $regex='#mathpublish\/img.php\?img=([a-f0-9]+)\"#';


			if(preg_match($regex,$xhtml,$matches)) {

				 $cache = getcachename($matches[1], '.mathpublish.png');
				 $name=$renderer->copy_media($cache,true);

				 $name = 'src="../' . $name . '" ' ;
				 $regex = '#src\s*=.*?mathpublish\/img.php\?img=([a-f0-9]+)\"#';
				 $xhtml = preg_replace($regex, $name ,$xhtml );

			}
		}

 	    function epub_check_for_mathjax(&$result) {

             if(epub_is_installed_plugin('mathjax_protecttex') ) {
             $plugin =& plugin_load('syntax','mathjax_protecttex');
             $url = $plugin->getConf('url');
              if(preg_match("#^//#",$url)) {
                  $url = "http:" . $url;
              }
             $config = $plugin->getConf('config');

$result .= <<<MATHJAX

<script type="text/x-mathjax-config" charset="utf-8">/*<![CDATA[*/
$config
}
);
/*!]]>*/</script>
<script type="text/javascript" charset="utf-8" src="$url"></script>

MATHJAX;
            }
        }


        function epub_save_namespace($ns="") {
           static $namespace;
           if($ns !== false)  {
              $namespace = $ns;
           }
           return $namespace;
        }

        function epub_checkfor_ns($name, &$pages, &$titles) {
            $name = rtrim($name);

            $n = strrpos($name,'*',-1);
            if(!$n) return;
             array_shift($pages);  // remove namespace id:  namespace:*

            $ns = wikiFN($name.':tmp');

            $path_parts= pathinfo($ns);
            $dir = $path_parts['dirname'];
            echo "Directory: $dir\n";
            $paths = glob("$dir/*.txt",GLOB_NOSORT);
            $helper = new  helper_plugin_epub();
            if($helper->get_conf('sort')){
                rsort($paths);
            }
            else { echo "not sorting files in namespace: $ns\n";}
             $_pages = array();
             $_titles = array();

            $ns = rtrim($name,'*');
            foreach ($paths as $path) {
                 $_pages[] = $ns . basename($path, '.txt');
            }
            $title_page = array_shift($titles);
            array_shift($titles);    // remove namespace asterisk from titles list

            for ($i=0; $i<count($_pages); $i++) {
               array_unshift ($pages , $_pages[$i]);
               $_titles[$i] = basename($_pages[$i], '.txt');
               $elems = explode(':',$_titles[$i] );
               $_titles[$i] = $elems[count($elems)-1];
               $_titles[$i] = ucwords(str_replace('_',' ',$_titles[$i]));
               array_unshift ($titles , $_titles[$i]);
            }
            array_unshift($titles,$title_page);

            echo "Found following pages in $name namespace: \n";
            print_r($_pages);
            echo "Saving namespace pages\n";
            epub_save_ns_pages($_pages);
            echo "Created following titles: \n";
            print_r($_titles);

        }

        function epub_save_ns_pages($_pages="") {
             static $pages;
             if($_pages) {
                 $pages = $_pages;
             }
             return $pages;
        }

		function epub_isWindows() {
		   return (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
		}

        function epub_update_progress($msg=null) {
            static $user;
            static $dir;
            static $progress_file;
            if(!$msg && $progress_file) {
                unlink($progress_file);
            }
            if(!$user) {
                $user= rawurldecode($_POST['user']);
                if($user) $user=cleanID($user);
            }
            if(!$dir) {
                if($user)  {
                   $dir = epub_get_metadirectory($user);
                }
                else $dir = epub_get_metadirectory();
                $dir = rtrim($dir,'/');
                $dir = dirname($dir . ".meta") . '/';
                $progress_file = $dir . "progress.meta";
            }

            if($progress_file && $msg) {
                io_saveFile($progress_file,"$msg\n");
            }

        }

         function epub_check_perm($id) {
            $id= cleanID(rawurldecode($id));
            $acl = auth_quickaclcheck($id);
            return $acl;
          }

          function epub_clean_name($name) {
              return ltrim($name,'_');
          }

        function is_external_uri($uri) {
            return
                strpos($uri,'http://') === 0 ||
                strpos($uri,'https://') === 0;
        }

        function prepend_before_file_extension($path, $prepend) {
            $info = pathinfo($path);
            $dir = $info['dirname'];
            $result = $dir ?
                $dir.DIRECTORY_SEPARATOR :
                '';
            $result .= $info['filename'].$prepend;
            $ext = $info['extension'];
            if ($ext)
                $result .= '.'.$ext;
            return $result;
        }

        function parse_tag_attribute($tag, $attribute_name) {
            $index = strpos($tag, $attribute_name);
            if (!$index)
                return NULL;
            $value = substr($tag, $index + strlen($attribute_name));
            $value = trim($value);
            $index = strpos($value, '=');
            if ($index !== 0)
                return NULL;
            $value = substr($value, 1);
            $value = trim($value);
            $index = strpos($value, '"');
            if ($index !== 0)
                return NULL;
            $value = substr($value, 1);
            $index = strpos($value, '"');
            if (!$index)
                return NULL;
            $value = substr($value, 0, $index);
            return $value;
        }

        function parse_tag_attribute_int($tag, $attribute_name) {
            $text = parse_tag_attribute($tag, $attribute_name);
            if (!$text)
                return NULL;
            $value = intval($text);
            return $value;
        }
