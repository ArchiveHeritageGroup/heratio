# LinkedIn post - The Backup That Was Never a Backup

Last week I went looking for a database binary log from August. The directory was empty. It had been empty since 13 August.

The nightly dumps had run the whole time. Every one of them landed, on schedule, on the NAS.

What was missing was the part that turns a nightly dump into actual recovery: the binary logs that replay the hours between one dump and the next. Without them, "restore" means accepting the loss of everything since 02:00. The recovery point objective we had written down was fiction, and had been for five weeks.

The cause was dull, which is rather the point. MySQL's data directory is mode 0700 and owned by mysql. The archiver ran as www-data, checked whether each log was readable, found that it was not, wrote a warning and moved on to the next one. About 1,170 warnings an hour. Five weeks of that, while 1,171 logs and 79 GB piled up untouched.

So the system reported its own failure, continuously, in the right place, for over a month. It may as well have said nothing. An alert nobody reads is not an alert, it is a log line with ambitions.

Two things worth taking from it.

A backup you have never restored from is a hypothesis, not a backup. Test the restore. The exit code of the backup job tells you the job ended, not that anything survived.

And watch the volume of your warnings, not just their content. Anything repeating a thousand times an hour is either an emergency or noise, and both of those are defects.

The fix turned out to be smaller than the diagnosis: pull the logs over the replication protocol instead of off the disk, which needs no access to the data directory at all. Archiving has been running since Thursday and the backlog is clearing.

Worth asking your own team this week: when did we last actually restore from a backup, all the way, and time it?

#DigitalPreservation #RecordsManagement #DataGovernance #Backup
