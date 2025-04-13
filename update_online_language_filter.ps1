# PowerShell script to update online.html with language filter feature
Write-Host "Updating online.html with language filter feature..."

# Create a backup of the current online.html file
Copy-Item -Path "online.html" -Destination "online.html.bak" -Force
Write-Host "Backup created: online.html.bak"

# Copy the updated online.html to a temporary file for uploading
Copy-Item -Path "online.html" -Destination "temp_online.html" -Force

# Use Git to add, commit, and push the changes
git add online.html
git commit -m "Add language filter feature to online.html"
git push origin master

Write-Host "Language filter feature has been updated and pushed to GitHub" 