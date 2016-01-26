#include <stdio.h>
#include <stdlib.h>
#include <sys/types.h>
#include <unistd.h>
#include <string.h>

// 用来切换到其他帐号执行php命令
int main(int argc, char *argv[])
{
    if (argc < 3) {
        printf("must supply 2 args\n");
        exit(1);
    }
    if (strcmp(argv[1], "php") != 0) {
        printf("the first arg must equal php\n");
        exit(1);
    }

	uid_t uid, euid;
	uid = getuid() ;
	euid = geteuid();

	// 如果设置权限报错，则退出
	if( setreuid(euid, uid) ) {
	    printf("setreuid error\n");
	    exit(1);
	}

	int i;
	char sh[300] = "";
	for(i = 1; i < argc; i++)
	{
		strcat(sh, argv[i]);
		strcat(sh, " ");
	}
	// 切换到git帐号
	exit(system(sh));
}