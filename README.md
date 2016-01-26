# FireGit 简介

FireGit是一个基于php的git管理平台。基础架构为：`nginx`+`fcgiwrap`+`fcgi`+`php`+`mysql`。


## fcgi配置

```
# /etc/rc.local
su git -c "/usr/local/sbin/fcgiwrap -c 5 -s tcp:0.0.0.0:9200 &"
```

## Nginx配置

### conf/nginx.conf

```nginx

server {
    server_name firegit.com;
    listen      80;
    root        /home/git/repos/;

    client_max_body_size 250m;
    
    location / {
        root /home/git/repos/public/;
        if (!-e $request_filename) {
            rewrite . /index.php;
        }
    }

    if ($uri ~ ^\/([^\/]*)\/([^\.\/]*)\.git($|\/)) {
        set $git_group $1;
        set $git_name $2;
    }

    location ~ /info/refs$ {
        include git.conf;
    }

    location ~ /git-receive-pack$ {
        if ( $request_method != "POST") {
            return 403;
        }
        include git.conf;
    }

    location /index.php {
        echo $document_root;
        fastcgi_pass    127.0.0.1:9001;
        fastcgi_param   SCRIPT_FILENAME $document_root/index.php;
        include         fastcgi_params;
    }
}

```

### conf/git.conf
```nginx
proxy_read_timeout      600;
proxy_connect_timeout   600;
proxy_redirect          off;

auth_basic "Restricted"; 
auth_basic_user_file /home/git/repos/$git_group/$git_name.auth;

fastcgi_pass    127.0.0.1:9200; 
include         fastcgi_params;
fastcgi_param   SCRIPT_FILENAME     /usr/libexec/git-core/git-http-backend;
fastcgi_param   FIREGIT_GROUP       $git_group; 
fastcgi_param   FIREGIT_NAME        $git_name; 

# export all repositories under GIT_PROJECT_ROOT

fastcgi_param   GIT_HTTP_EXPORT_ALL "";
fastcgi_param   GIT_PROJECT_ROOT    $document_root;
fastcgi_param   PATH_INFO           $uri;
```