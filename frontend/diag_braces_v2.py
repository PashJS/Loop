def check_braces(filename):
    with open(filename, 'r') as f:
        content = f.read()
    
    open_count = 0
    close_count = 0
    lines = content.split('\n')
    for i, line in enumerate(lines):
        for char in line:
            if char == '{':
                open_count += 1
            elif char == '}':
                close_count += 1
                if close_count > open_count:
                    print(f"Error: Found extra closing brace at line {i+1}")
    
    print(f"Total Open: {open_count}")
    print(f"Total Close: {close_count}")
    if open_count != close_count:
        print("ERROR: Braces are NOT balanced!")
    else:
        print("Success: Braces are balanced.")

check_braces('c:/MAMP/htdocs/FloxWatch/frontend/layout.css')
