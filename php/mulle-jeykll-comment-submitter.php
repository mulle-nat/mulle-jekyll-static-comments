<?php

// mulle-jeykll-comment-submitter.php -- Receive comments, commit and push them into a
//                                       git repository
//
// Copyright (C) 2015 Nat! Mulle kybernetiK
// Copyright (C) 2011 Matt Palmer <mpalmer@hezmatt.org>
//
//  This program is free software; you can redistribute it and/or modify it
//  under the terms of the GNU General Public License version 3, as
//  published by the Free Software Foundation.
//
//  This program is distributed in the hope that it will be useful, but
//  WITHOUT ANY WARRANTY; without even the implied warranty of
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
//  General Public License for more details.
//
//  You should have received a copy of the GNU General Public License along
//  with this program; if not, see <http://www.gnu.org/licences/>


require_once __DIR__ . '/safehtml.php';

class MulleJekyllCommentSubmitter
{
   // change this: where comments are stored
   public $comment_directory = ".";
   
   // change this: timezone of the comment system
   public $comment_timezone = "Europe/Berlin";
   
   // subdirectory structure to place the comment (as a date format)
   // default will be like 2015/file.yaml
   // category sadly unvailable
   public $comment_subdir    = "Y";

   // what post fields from the form to store into the yaml header of the comment
   // "comment" is a given as the comments body
   public $comment_keys          = array( "email", "name"); 
   public $comment_required_keys = array("name"); 

   // default file extension for comment file
   public $comment_extension = "yaml";
   
   // Format of the date you want to use in your comments.  See
   // http://php.net/manual/en/function.date.php for the insane details of this
   // format.
   public $comment_date_format = "d-m-Y H:i";

   public $log_file             = NULL;

   public $submit_script        = NULL ;  //  __DIR__ . "/submit-mail.sh";
   public $submit_in_background = false;

   function   log( $output)
   {
      if( $this->log_file === NULL)
 	return; 
      
      file_put_contents( $this->log_file, date('d.m.Y H:i:s') . ":" . $output . "\n", FILE_APPEND);
   }

   function   logKeyValue( $key, $value)
   {
      $output=$value; 
      if( is_array( $value))
      {
         $output=NULL;
         // http://stackoverflow.com/questions/173400/how-to-check-if-php-array-is-associative-or-sequential
         if( $value !== array_values($value))
         {
             foreach( $value as $key => $line)
             {
                if( $output)
                   $output=$output . "\n";
                $output=$output . "$key" . "=" . $line;
             }
         }
         else
         {
            foreach( $value as $line)
            {
                if( $output)
                   $output=$output . "\n";
                $output=$output . $line;
            }
         }
      }

      if( $key != NULL)
         $output="$key" . "=" . "$output";

     $this->log( $output); 
   }

   
   function   compose_yaml( $post)
   {
      $this->logKeyValue( "_POST[]", $post);

      if( $post === NULL)
         throw new Exception( 'post cant be NULL');

      $post_id = $post["post_id"];
      if( "$post_id" === "") 
         throw new Exception( "U sent it rrrong");
      
      date_default_timezone_set( $this->comment_timezone);
      
      $msg  = "post_id: $post_id\n";
      $msg .= "date: " . date( $this->comment_date_format) . "\n";
      
      $comment=$post[ "comment"];

      //
      // extract only known keys
      //
      foreach( $this->comment_keys as $key)
      {
         if( array_key_exists( $key, $post))
         { 
            $value = $post[ $key];   
            if( strstr($value, "\n") != "")
            {
               // Value has newlines... need to indent them so the YAML
               // looks right
              $value = str_replace("\n", "\n  ", $value);
            }
            
            // It's easier just to single-quote everything than to try and work
            // out what might need quoting
            $value = "'" . str_replace("'", "''", $value) . "'";
            $msg .= "$key: $value\n";
         }
         else
         {
            if( in_array( $key, $this->comment_required_keys))
                throw new Exception( 'U did it not enough'. " ($key)");               
         }
      }
      return array( "header" => $msg, "body" => $comment, "post_id" => $post_id);
   }
   
   
   public function   tidy_html_content( $comment)
   {
      //
      // first run it through tidy to make the HTML proper
      //
      $escaped_comment=escapeshellcmd( "$comment");
      if( $escaped_comment === "")
         throw new Exception('Escape did it rrrong');
      
      if( ! is_executable( "/usr/bin/tidy"))
         throw new Exception('Tidy is missing');

      echo exec( "echo '$escaped_comment' | /usr/bin/tidy -raw -q -b -c -u -asxhtml --enclose-block-text yes --drop-proprietary-attributes yes --hide-comments yes --show-warnings no --show-body-only yes", $tidied, $rval);

      if( $rval > 1)
      {
         error_log( "tidy objected to :" .  $comment);
         throw new Exception('U did it rrong');
      }

      $comment="";
      foreach( $tidied as $line)
         $comment="$comment" . "$line";
      
      return $comment;
   }
   
   
   public function   ensure_safe_html_content( $comment)
   {
      //
      // now check that HTML is safe
      //
      $checker = new SafeHtmlChecker;
      $checker->check('<all>' . $comment . '</all>');
      if( ! $checker->isOK())
      {
         error_log( "safe html objected to :" .  $comment);
         foreach ($checker->getErrors() as $error) 
           error_log( $error);
         throw new Exception('U did it rrong');
      }
      return $comment;
   }
  
   //
   // get a filename. This scheme allows for up to 100000 comments
   // probably gets really slow when close to fillage
   // we abort when we retry for 128 times
   //
   function   acquire_comment_filename( $post_id)
   {
      $name=basename( $post_id);

      $dir="";
      if( strlen( $this->comment_subdir) !== 0)
      {
      $dir=date( $this->comment_subdir);
      if( ! is_dir($dir))
         mkdir( $dir, 0777, true);  // this raises if fails
      }
      
      $n=0;
      $max=128;
      do
      { 
         $filename = "$dir" . "/" . "$name" . "_" . substr( uniqid( rand(), true), 0, 5) . "." . "$this->comment_extension";
         if ( ! file_exists( $filename))
            break;
         
         $n=$n+1;
         if( $n >= $max)
            throw new Exception('U did it too much');
      }
      while( true);
      
      return $filename;
   }
   
   
   function  _yaml_file_from_post( $post)
   {
      $yaml = $this->compose_yaml( $post);
      if( $yaml[ "body"] === "")
         throw new Exception('U did it rrrong.');

      $yaml[ "body"] = $this->tidy_html_content( $yaml[ "body"]);
      if( $yaml[ "body"] === "")
         throw new Exception('U did it rrrong.');
      
      $yaml[ "body"] = $this->ensure_safe_html_content( $yaml[ "body"]);

      $filename = $this->acquire_comment_filename( $yaml[ "post_id"]);
      file_put_contents( $filename, "---\n" . $yaml[ "header"] . "---\n" . $yaml[ "body"] . "\n");

      $this->logKeyValue( "file", $filename);
   
      return $filename;   
   }


   public function   yaml_file_from_post( $post)
   {
      $oldPath = getcwd();
      $filename=NULL;
      try
      {
         if( ! is_dir( $this->comment_directory))
            throw new Exception("I forgot to make $this->comment_directory");
         if( ! is_writable( $this->comment_directory))
            throw new Exception("I set $this->comment_directory up rrrong.");

         chdir( $this->comment_directory);
         $filename=$this->_yaml_file_from_post( $post);
      }
      catch( Exception $e)
      {
         error_log( $e->__toString());
      }
      chdir( $oldPath);
      return $filename;
   }


   function   _submit( $filename)
   {
      if( $filename === NULL)
         throw new Exception( 'filename cant be NULL');
	
      if( $this->submit_script == NULL)
         $this->submit_script = __DIR__ . "/submit.sh";

      if( ! is_executable( $this->submit_script))
         throw new Exception( "I installed $this->submit_script rrrongg");

      if( $this->submit_in_background)
      {
         $this->log( "$this->submit_script $filename $this->log_file &");
         exec( "$this->submit_script $filename $this->log_file > /dev/null 2>&1 &", $complain, $rval);
      }
      else
      {
         $this->log( "$this->submit_script $filename $this->log_file");
         exec( "$this->submit_script $filename $this->log_file", $complain, $rval);
      }

      if( $rval != 0)
         throw new Exception( "Submission failed");
   }


   public function   submit( $filename)
   {
      $oldPath = getcwd();
      try
      {
         chdir( $this->comment_directory);
         $this->_submit( $filename);
      }
      catch( Exception $e)
      {
         error_log( $e->__toString());
         return $e;
      }
      return NULL;
   }
}
?>

