import os
import shutil
import subprocess

def update_site():
    print("Updating social icons and links in online.html...")
    
    # Create a backup
    shutil.copy2('online.html', 'online.html.bak')
    print("Backup created: online.html.bak")
    
    # Git commands
    try:
        # Add the file
        subprocess.run(['git', 'add', 'online.html'], check=True)
        print("Added online.html to git staging")
        
        # Commit the changes
        subprocess.run(['git', 'commit', '-m', "Update social icons to monochrome and fix 'O seu' button link"], check=True)
        print("Committed changes")
        
        # Push to remote
        subprocess.run(['git', 'push', 'origin', 'main'], check=True)
        print("Pushed changes to GitHub")
        
        print("Social icons and links have been updated and pushed to GitHub")
    except subprocess.CalledProcessError as e:
        print(f"Error during Git operations: {e}")

if __name__ == "__main__":
    update_site() 