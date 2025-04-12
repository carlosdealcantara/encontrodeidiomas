# PowerShell script to update online.html social icons and link fixes
Write-Host "Updating social icons and links in online.html..."

# Create a backup of the current online.html file
Copy-Item -Path "online.html" -Destination "online.html.bak" -Force
Write-Host "Backup created: online.html.bak"

# Use Git to add, commit, and push the changes
git add online.html
git commit -m "Update social icons to monochrome and fix 'O seu' button link"
git push origin main

Write-Host "Social icons and links have been updated and pushed to GitHub" 