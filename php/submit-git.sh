#! /bin/sh

REMOTE=origin
BRANCH=master
USER=www-data
EMAIL=www-data@muhbuntu.mulle-kybernetik.com

FILE=$1
LOGFILE=${2:-"/var/log/mulle-comments.log"}

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

log "checking..."
current=`git config --global --get user.name`

# from now on stuff must work
set -e
if [ "$current" != "$USER" ]
then
   log "configuring..."
   git config --global user.name "$USER"  >> "$LOGFILE" 2>&1
   git config --global user.email "$EMAIL" >> "$LOGFILE" 2>&1
fi

log "submitting..."
git add "$FILE" >> "$LOGFILE" 2>&1
git commit -m "web submission" "$FILE" >> "$LOGFILE" 2>&1
git push $REMOTE $BRANCH >> "$LOGFILE" 2>&1
