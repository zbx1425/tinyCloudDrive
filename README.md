# Tiny Cloud Drive

由 [Tiny File Manager][ https://tinyfilemanager.github.io/ ] 魔改而来的 _较为_ 轻量化的PHP多用户注册制云存储平台套件



## 安装

直接将  `index.php` `account.php` `translation.json `放置在您的服务器即可。

可在PHP7运行，PHP5大概可行但尚未测试。您的php进程必须拥有对安装文件夹的读、写、执行权限。

必须您先前安装的PHP扩展有：

- json
- openssl
- session

建议您安装的扩展有：

- ctype
- fileinfo
- iconv
- mbstring



## 配置

您可在两份php文件中查找  `CONFIG_THIS` 字样，以将相应内容替换为您站点的内容。

如果您需要发送Email进行用户验证，请您自行下载安装 [PHPMailer][ https://github.com/PHPMailer/PHPMailer ] 库，并相应调整 `account.php` 第4~23行：

```php
/*   4 */ $enableEmail = true; //CONFIG_THIS
/* ... */ // 您的账号，主题，内容等
/*  23 */ require_once "/PATH/TO/PHPMailer/Installation"; //CONFIG_THIS
```

如果您使用第三方服务发送Email，请依照您所使用服务商所提供的文档完成配置，并替换77-108行的代码。



## 安全配置

为避免上传CGI脚本造成的任意代码执行，您应当配置服务器关闭**安装目录的子目录**的CGI脚本执行。

配置内容（以nginx为例）:

```
location ~ ^\..*/.*\.php$ {
    return 403;
}
location ~ ^/[PATH_TO_INSTALLATION]/.+/.+\.php$ {

}
```

