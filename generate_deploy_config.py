import base64

input_file = "c:\\MAMP\\htdocs\\FloxWatch\\config_optimized.php"
output_vbs = "c:\\MAMP\\htdocs\\FloxWatch\\deploy_config_final.vbs"
remote_host = "82.208.23.150"
remote_path = "/var/www/html/FloxWatch/backend/config.php"
password = "hPwT865FSq31Z"

with open(input_file, "rb") as f:
    content = f.read()

b64_content = base64.b64encode(content).decode('utf-8')

vbs_content = f'''
set sh = CreateObject("WScript.Shell")
remote_host = "{remote_host}"
password = "{password}"

' Base64 content of optimized config
b64 = "{b64_content}"

' Deploy command
inner_cmd = "echo " & b64 & " | base64 -d > {remote_path} && chown www-data:www-data {remote_path}"

ssh_cmd = "ssh -o StrictHostKeyChecking=no root@" & remote_host & " """ & inner_cmd & """"

full_cmd = "cmd /c " & ssh_cmd & " > c:\\MAMP\\htdocs\\FloxWatch\\deploy_config_result.txt 2>&1"

sh.Run full_cmd, 1, False
WScript.Sleep 8000
sh.SendKeys password & "{{ENTER}}"
WScript.Sleep 5000
'''

with open(output_vbs, "w") as f:
    f.write(vbs_content)

print("VBScript generated.")
