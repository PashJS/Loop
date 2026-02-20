
import re
import subprocess
import os

def check_syntax():
    with open('c:/MAMP/htdocs/FloxWatch/frontend/chat.php', 'r', encoding='utf-8') as f:
        content = f.read()

    # extract the script block
    # We know it starts around line 1561 and ends at 4830.
    # But let's use regex to find the last large script block.
    
    # Logic: Find <script> that is NOT the src one.
    # The one starting at 1561 is <script> (no src).
    
    matches = list(re.finditer(r'<script>(.*?)</script>', content, re.DOTALL))
    if not matches:
        print("No inline script found")
        return

    # Use the largest match (the main logic)
    script_content = max(matches, key=lambda m: len(m.group(1))).group(1)
    
    # Replace PHP tags with placeholders
    # <?php echo json_encode($var); ?> -> "PHP_VAR"
    # <?php echo ... ?> -> "PHP_ECHO"
    
    # Simple replacement: replace all <?php ... ?> sequences with "0" or null.
    # We need to be careful about replacing inside strings vs code.
    # Most PHP inside this JS is echoing values into variables.
    
    # Regex for PHP tags
    script_content = re.sub(r'<\?php.*?\?>', 'null', script_content, flags=re.DOTALL)
    
    # Write to tmp file
    with open('tmp_check.js', 'w', encoding='utf-8') as out:
        out.write(script_content)
        
    # Run node check
    try:
        result = subprocess.run(['node', '-c', 'tmp_check.js'], capture_output=True, text=True)
        if result.returncode == 0:
            print("Syntax OK")
        else:
            print("Syntax Error:")
            print(result.stderr)
    except Exception as e:
        print(f"Error running node: {e}")

if __name__ == '__main__':
    check_syntax()

