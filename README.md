# IPv6Status
Typecho IPv6状态标识插件 
# 📌 简介

该插件会在网站的页脚显示一个精美的IPv6状态标识

- **适配Typecho1.3.0版本，其他版本未做测试**
- **自动检测是否支持IPv6**
- **实时识别访客当前使用的IP协议版本**（IPv4或IPv6）
- **根据检测结果动态切换标识样式**

无需复杂配置，开箱即用，且完全适配暗色模式与移动端。

## ✨ 版本更新记录

- ** V1.0.1,修改为单文件版本，更轻便。
- ** V1.0.0,初始版本

##  截图

<img width="240" height="41" alt="image" src="https://github.com/user-attachments/assets/01e87848-7314-443b-aae4-4216911c7e6d" />
<img width="221" height="39" alt="image" src="https://github.com/user-attachments/assets/90579c7c-77fd-49cd-aa6c-562e2c5f0ce5" />
<img width="281" height="49" alt="image" src="https://github.com/user-attachments/assets/ad7dc6c1-bf6c-40fd-845f-7504bfa3809b" />
<img width="86" height="28" alt="image" src="https://github.com/user-attachments/assets/9f0e16bc-89fb-455c-8c56-56d2d4064e1a" />
<img width="70" height="22" alt="image" src="https://github.com/user-attachments/assets/5f50be62-5275-4b95-8207-dd286b6a809b" />
<img width="63" height="23" alt="image" src="https://github.com/user-attachments/assets/370719d9-7c3f-45da-960e-7fd9f4ef9286" />




## ✨ 功能特性

- ✅ **自动检测网站IPv6支持**。
- ✅ **多种显示样式**：提供**徽章样式**、**毛玻璃样式**、**信号样式**、**圆点样式**，可在后台自由切换。
- ✅ **智能前台渲染**：
  - 若网站不支持IPv6→前台不输出任何标识，避免误导。
  - 若网站支持 IPv6→根据访客IP显示标识。
- ✅ **后台可视化配置**：
  - 样式选择
  - 是否显示状态文字
  - 暗色模式适配（跟随系统）
  - 插入方式（自动插入页脚 / 手动调用）
- ✅ **完美兼容CDN**：自动处理IPv4映射地址，准确识别访客真实IP。
## 🔧 安装方法

1. 将本插件文件夹上传到plugins目录。
2. 登录Typecho后台启用。
3. 点击**设置**，根据需求配置

**提示**：未配置IPv6地址的不支持使用，也不支持显示，后续配置IPv6地址后，通过重新检测方式启用。

---
