
import sys

def check_braces(filepath):
    try:
        with open(filepath, 'r') as f:
            content = f.read()
            
        open_braces = []
        for i, char in enumerate(content):
            if char == '{':
                open_braces.append(i)
            elif char == '}':
                if not open_braces:
                    print(f"Unmatched closing brace at character {i}")
                    # Print context
                    start = max(0, i - 50)
                    end = min(len(content), i + 50)
                    print(f"Context: ...{content[start:end]}...")
                    return False
                open_braces.pop()
        
        if open_braces:
            print(f"Unmatched opening braces at characters: {open_braces}")
            for pos in open_braces:
                start = max(0, pos - 50)
                end = min(len(content), pos + 50)
                print(f"Context for pos {pos}: ...{content[start:end]}...")
            return False
            
        print("Braces are balanced.")
        return True
    except Exception as e:
        print(f"Error: {e}")
        return False

if __name__ == "__main__":
    if len(sys.argv) > 1:
        check_braces(sys.argv[1])
    else:
        print("Please provide a filepath.")
