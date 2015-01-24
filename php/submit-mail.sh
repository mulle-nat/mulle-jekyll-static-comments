#! /bin/sh

FILE="$1"
LOGFILE=${2:-"/var/log/mulle-comments.log"}
RECEIVER=${3:-"root"}
SUBJECT=${4:-"Here's a new comment for you"}


log()
{
   echo `date '+%d.%m.%Y %H:%M:%S'` "submit.sh:" $*  >> "$LOGFILE"
}


if [ ! -r "$1" ]
then
   log "inputfile \"$1\" is not readable" 
   exit 1
fi


at_exit()
{
   if [ $1 -eq 0 ]
   then
      log "submitted" 
   else
      log "failed"
   fi
}

# if something fails bail
trap 'at_exit $?' EXIT


mail -s "$SUBJECT" "$RECEIVER" < $FILE

