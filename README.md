# PHPSync

Shell script folder to folder synchronization tool that provides similar features as `rsync`. PHPSync however is able to add compression to the backed up data. Note that unlike `rsync`, the compression is not just for transfer purposes. This will compress the backed up data that is being written to the disk.

```sh
phpsync -d --log /var/log --algo xz /path/to/src /path/to/backup
```

The above example will sync the `src` folder with the `backup` folder and compress all backed up data using LZMA. If you want to reverse the sync to restore files from a backup, add the `x` flag and reverse the folder order. Only changed files will be restored.

```sh
phpsync -dx --log /var/log --algo xz /path/to/backup /path/to/src
```

PHPSync keeps track of any change, incl. file/folder permissions, ownership etc.

Currently it supports 3 compression algo's, `xz`, `gz` and `bz`. You can also parse `none` if you do not wish to use compression. You can always change your settings without having to re-backup everything. Any configuration change will only take affect on new backed up files. You can see a list of options if you execute PHPSync without any arguments. 

> Note that `xz` will only work on Linux. Any other platform, use `bz` or `gz`.

At the moment PHPSync is only able to sync two folders. There are no filter options or other additional features. It may come at some point.

> Compression is only added to files larger than 256 bytes. Compression itself takes up additional space due to headers and such, so adding compression to very small files would instead increase their size rather than make them smaller.
