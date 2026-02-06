# GitHub CLI Authentication Setup

## Quick Authentication (Interactive)
```bash
gh auth login
```

When prompted:
1. Choose: GitHub.com
2. Choose: HTTPS (recommended)
3. Authenticate with: Login with a web browser (easiest) or Paste authentication token
4. If browser: Copy the code shown and open the URL
5. If token: Use a personal access token with 'repo' scope

## Alternative: Authentication with Token (Non-interactive)
```bash
# If you have a token ready:
echo "YOUR_PERSONAL_ACCESS_TOKEN" | gh auth login --with-token

# Or set it as environment variable:
export GITHUB_TOKEN="YOUR_PERSONAL_ACCESS_TOKEN"
gh auth login
```

## Verify Authentication
```bash
# Check authentication status
gh auth status

# Test by viewing your repos
gh repo list --limit 5
```

## Using GitHub CLI with Your PartyPool Repo

### Clone the repository
```bash
gh repo clone myelinviolin/PartyPool
```

### Make changes and commit
```bash
cd PartyPool
# ... make your changes ...
git add .
git commit -m "Your commit message"
```

### Push changes (no token needed!)
```bash
git push
# or
gh repo sync
```

### Create pull requests
```bash
gh pr create --title "My changes" --body "Description of changes"
```

### View repository in browser
```bash
gh repo view --web
```

## Benefits of GitHub CLI
- ✅ No need to share tokens in future sessions
- ✅ Credentials stored securely on your system
- ✅ Works with git commands automatically
- ✅ Additional features like creating issues, PRs, etc.
- ✅ Automatic token refresh

## Common Commands
```bash
# View repo info
gh repo view myelinviolin/PartyPool

# Create an issue
gh issue create --title "Bug report" --body "Description"

# List issues
gh issue list

# Clone any of your repos
gh repo list
gh repo clone REPO_NAME

# Create a new repo
gh repo create new-project --public
```

## Troubleshooting
If authentication fails:
1. Make sure you're using a token with 'repo' scope
2. Try logging out first: `gh auth logout`
3. Clear cached credentials: `gh auth refresh`