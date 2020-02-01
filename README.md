# Tiny Cloud Drive

由 [Tiny File Manager](https://tinyfilemanager.github.io/) 魔改而来的 _较为_ 轻量化的PHP多用户注册制云存储平台套件

A lightweight multi-user registerable PHP cloud storage website kit based on [Tiny File Manager](https://tinyfilemanager.github.io/)

![Preview Image](https://gitcdn.link/repo/zbx1425/tinyCloudDrive/master/screenshot.png)

![Preview Image](https://gitcdn.link/repo/prasathmani/tinyfilemanager/master/screenshot.gif)

## 使用指南 Getting Started

该套件可被快速部署在支持php的服务器上。

### 项目使用条件 Prerequisites

可在PHP7上运行。PHP5可能可行但尚未测试。

您的php进程必须拥有对安装文件夹的读、写、执行权限。

必须您先前安装的PHP扩展有：

- json
- openssl
- session

建议您安装的扩展有：

- ctype
- fileinfo
- iconv
- mbstring

### 安装 Installation

直接将  `index.php` `account.php` `translation.json account.json ` 放置在您的服务器即可。**请勿重命名上述文件。**

Install the kit by placing `index.php` `account.php` `translation.json` `account.json` to your web server at where you would like. **Do not rename these scripts.**

要进行配置，您可在`index.php` `account.php`中查找  `CONFIG_THIS` 字样，并编辑相应内容。

To config the kit, look for `CONFIG_THIS` in `index.php` and `account.php`, and edit the relative contents.

编辑 `index.php` 中`$CONFIG`即可配置语言、错误显示、隐藏文件显示等TinyFileManager功能。**由于懒惰，`account.php`系根据行号查找配置信息，请将该配置变量留在第3行。**

To Use English Interface, edit `$CONFIG` in `index.php` by changing `"lang":"zh-CN"` to `"lang":"en"`. **Please remain this variable at line 3 and do not move it elsewhere.**

如果您需要通过SMTP发送Email进行用户验证，请自行下载安装 [PHPMailer](https://github.com/PHPMailer/PHPMailer) 库，并相应调整 `account.php` 第4~23行：

If you need to send emails by SMTP for user registration, please download and install [PHPMailer](https://github.com/PHPMailer/PHPMailer), then adjust line 4 ~23 of `account.php`:

```php
/*   4 */ $enableEmail = true; //CONFIG_THIS
/* ... */ // 配置您的账号，主题，内容等
/*  23 */ require_once "/PATH/TO/PHPMailer/Installation"; //CONFIG_THIS 引入PHPMailer
```

如果您使用邮件发送服务发送Email，请依照您所使用服务商所提供的文档完成配置，并替换77-108行的代码。

If you are using sendmail services instead of SMTP, please config according to the documentation of your service provider, and replace line 77~108 accordingly.

**如需使用Email验证，请立刻修改`account.php`中第4行的`$encryptMagic`数组！请使用三个及以上随意数值替换默认数值，否则不怀好意者可伪造报文越过Email校验！**

**If you intend to use email validation, please REPLACE THE `$encryptMagic` ARRAY IN `account.php` AT LINE 4 IMMEDIATELY! Please use three or more random number to replace the default ones, otherwise some badass would be able to fake a link and bypass the validation!**

为尽可能使配置轻量化，本套件不使用数据库，用户数据直接存储于`account.json`中。如需使用数据库，请根据您使用的数据库软件修改`account.php`中`readUsers()`和`writeUsers($raw)`函数。

Tiny File Manager的`$auth_users `与 `$directories_users`已由本套件自动配置，不能人工修改。

### 使用示例 Usage example

访问您的服务器上该套件的安装路径，您应该能够看到本套件主页。

![Preview Image](https://gitcdn.link/repo/zbx1425/tinyCloudDrive/master/screenshot.png)

注册并验证Email后即可登录使用云盘。每个用户的存储空间均相互独立，互不干扰。有关内置文件管理器的详细使用方式，详见 [Tiny File Manager文档](https://tinyfilemanager.github.io/docs/) 。



## 部署方法

为避免上传CGI脚本造成的任意代码执行，您应当配置服务器以关闭**本套件安装目录之子目录**的CGI脚本执行，及避免配置文件被直接读取。

配置内容（以nginx之.conf为例，在server一节增加以下内容）:

```
location ~ ^\..*/.*\.php$ {
    deny all;
}
location ~ ^/[PATH_TO_INSTALLATION]/.+\.json$ {
    deny all;
}
location ~ ^/[PATH_TO_INSTALLATION]/.+/.+\.php$ {

}
```

（`[PATH_TO_INSTALLATION]` 为 `index.php` 所在目录）



## 版本历史

- 0.1.0
  - 最初版本



## 关于作者

- zbx1425 - [zbx1425.tk](https://zbx1425.tk) - [zbx1425@outlook.com](mailto:zbx1425@outlook.com)



## 授权协议

按基础项目Tiny File Manager要求，本项目由GPLv3授权。 请参照 [LICENSE]( https://github.com/zbx1425/tinyCloudDrive/blob/master/LICENSE ) 了解更多细节。 