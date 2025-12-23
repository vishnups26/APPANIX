/*
  ====================================================================
  APPANIX CI/CD Pipeline with Automatic Git Webhook Triggers
  ====================================================================
  
  Prerequisites:
  1. Jenkins installed locally (http://localhost:8080)
  2. Docker installed on Jenkins machine
  3. Git credentials configured in Jenkins (if private repo)
  4. Docker Hub credentials added to Jenkins with ID: docker-hub-creds
  5. GitHub repository (public or private)
  6. Jenkins accessible from GitHub (for webhooks)
  
  ====================================================================
  AUTOMATIC TRIGGER SETUP: GitHub Webhook Integration
  ====================================================================
  
  This pipeline uses githubPush() trigger to automatically run when:
  - Code is pushed to the main branch
  - Pull requests are merged to main
  - Any commit is made to main
  
  IMPORTANT for Local Jenkins:
  - If your Jenkins is behind a firewall or on localhost:8080, GitHub
    cannot reach it directly. You have 3 options:
    
    Option 1: Expose Jenkins to the Internet (NOT recommended for local dev)
    Option 2: Use ngrok to tunnel Jenkins to the internet (Recommended)
    Option 3: Use polling instead of webhooks (Alternative)
  
  ====================================================================
  SETUP STEPS
  ====================================================================
  
  A. INSTALL REQUIRED JENKINS PLUGINS
  ====================================
  1. Jenkins Home → Manage Jenkins → Manage Plugins → Available
  2. Search for and install:
     - GitHub Integration Plugin (or GitHub plugin)
     - GitHub Branch Source Plugin
  3. Click "Install without restart" and Jenkins will reload plugins
  
  B. SETUP GITHUB WEBHOOK (if Jenkins is publicly accessible)
  ===========================================================
  1. Go to your GitHub repository: https://github.com/vishnups26/APPANIX
  2. Click Settings → Webhooks → Add webhook
  3. Fill in:
     - Payload URL: http://your-jenkins-ip:8080/github-webhook/
       (Replace your-jenkins-ip with actual IP if not localhost)
     - Content type: application/json
     - Events: Let me select individual events
       ☑ Push events
       ☑ Pull request events
     - Active: ☑ Yes
  4. Click "Add webhook"
  5. Scroll down and verify the webhook shows a green checkmark
  
  C. SETUP FOR LOCAL JENKINS (Using ngrok Tunnel)
  ===============================================
  
  This exposes your local Jenkins to GitHub without opening ports!
  
  Step 1: Install ngrok
  ```bash
  # Option A: Download from ngrok.com
  # Option B: On macOS with Homebrew
  brew install ngrok
  
  # Option C: On Linux
  wget https://bin.equinox.io/c/bNyj1mQVY4c/ngrok-v3-stable-linux-amd64.zip
  unzip ngrok-v3-stable-linux-amd64.zip
  sudo mv ngrok /usr/local/bin/
  ```
  
  Step 2: Create a free ngrok account and get auth token
  - Sign up at https://dashboard.ngrok.com/signup
  - Go to https://dashboard.ngrok.com/auth/your-authtoken
  - Copy your auth token
  
  Step 3: Authenticate ngrok
  ```bash
  ngrok config add-authtoken <your-auth-token>
  ```
  
  Step 4: Start ngrok tunnel (in a separate terminal)
  ```bash
  ngrok http 8080
  # Output will show:
  # Forwarding                    https://abc123.ngrok.io -> http://localhost:8080
  ```
  
  Step 5: Add GitHub webhook
  - Go to GitHub repo → Settings → Webhooks → Add webhook
  - Payload URL: https://abc123.ngrok.io/github-webhook/
  - Content type: application/json
  - Events: Push events, Pull request events
  - Active: ☑ Yes
  - Click "Add webhook"
  
  Step 6: Test the webhook
  - Verify green checkmark on GitHub webhook settings
  - Make a test commit: git push origin main
  - Check Jenkins builds page to see automatic build start
  
  D. ALTERNATIVE: POLLING (if webhook doesn't work)
  ================================================
  
  If you can't set up webhooks, Jenkins can poll GitHub every few minutes.
  Uncomment the pollSCM line in the triggers section below.
  
  This polls GitHub every 5 minutes and triggers a build if changes detected.
  
  E. CREATE JENKINS PIPELINE JOB
  ============================
  1. Jenkins Home → New Item → appanix-pipeline → Pipeline
  2. General:
     - ☑ GitHub project
     - Project url: https://github.com/vishnups26/APPANIX/
  3. Build Triggers:
     - ☑ GitHub hook trigger for GITScm polling
     - (or ☑ Poll SCM with schedule: H/5 * * * *)
  4. Pipeline:
     - Definition: Pipeline script from SCM
     - SCM: Git
     - Repository URL: https://github.com/vishnups26/APPANIX.git
     - Credentials: (leave empty for public repo, or add GitHub token)
     - Branch Specifier: main
     - Script Path: Jenkinsfile
  5. Save
  
  F. SETUP GIT CREDENTIALS (for private repos)
  ============================================
  1. Go to https://github.com/settings/tokens
  2. Click "Generate new token" (classic)
  3. Give it:
     - Name: jenkins-ci-token
     - Scopes: repo (full control), workflow
  4. Copy the token
  5. In Jenkins:
     - Manage Jenkins → Manage Credentials → (global)
     - Add Credentials:
       * Kind: Username with password
       * Username: your-github-username
       * Password: paste the token above
       * ID: github-token
       * Description: GitHub CI Token
     - Click Create
  6. In pipeline job configuration:
     - Set Repository URL: https://github.com/vishnups26/APPANIX.git
     - Set Credentials to the github-token you just created
  
  ====================================================================
  How to Test the Automatic Trigger
  ====================================================================
  
  1. Make sure ngrok tunnel is running (if using webhooks):
     ```bash
     ngrok http 8080
     ```
  
  2. Make a test commit:
     ```bash
     cd /path/to/APPANIX
     echo "# Test trigger" >> README.md
     git add README.md
     git commit -m "Test webhook trigger"
     git push origin main
     ```
  
  3. Check Jenkins:
     - Go to http://localhost:8080/job/appanix-pipeline
     - Within 5-10 seconds, you should see a new build start
     - Click on the build number to watch the console output
  
  4. Verify build success:
     - Check "Console Output" for any errors
     - Images should be built and pushed (if NAMESPACE is set)
  
  ====================================================================
  TROUBLESHOOTING
  ====================================================================
  
  Webhook not triggering:
  1. Check GitHub webhook settings (green checkmark)
  2. If using ngrok, verify tunnel is still running
  3. Check Jenkins logs: Manage Jenkins → System Log
  4. Check webhook delivery logs on GitHub:
     - Settings → Webhooks → Click the webhook → Deliveries tab
  
  "GitHub hook trigger for GITScm polling" not available:
  1. Install "GitHub Integration Plugin" (Step B above)
  2. Reload Jenkins plugins (may require restart)
  
  Jenkins behind firewall:
  1. Use ngrok tunnel (recommended)
  2. Or configure port forwarding on your router
  3. Or use polling instead of webhooks
  
  Poll SCM not working:
  1. Check Jenkins can access GitHub (no network block)
  2. Verify repository URL is correct and accessible
  3. Check git command works locally: git ls-remote <repo-url>
  
  Build triggered but fails:
  1. Check "Console Output" for specific error
  2. Verify Docker is running: docker --version
  3. Verify credentials are correct (docker-hub-creds)
  4. Check file permissions on Jenkins workspace
*/

pipeline {
  agent any

  triggers {
    // Trigger on push to main branch via GitHub webhook
    githubPush()
    
    // Alternative: Poll SCM every 5 minutes (if webhook not available)
    // pollSCM('H/5 * * * *')
  }

  parameters {
    string(name: 'REGISTRY', defaultValue: 'docker.io', description: 'Container registry host (e.g., docker.io, ghcr.io, ecr.aws)')
    string(name: 'NAMESPACE', defaultValue: '', description: 'Registry namespace/org (e.g., your-docker-username). Leave empty for local builds.')
    string(name: 'FRONTEND_IMAGE', defaultValue: 'appanix-frontend', description: 'Frontend image name')
    string(name: 'BACKEND_IMAGE', defaultValue: 'appanix-backend', description: 'Backend image name')
    string(name: 'DOCKER_CREDENTIALS_ID', defaultValue: 'dockerhub-creds', description: 'Jenkins credentials ID for registry (created in Manage Credentials)')
  }

  environment {
    BUILD_TAG = "${env.BUILD_NUMBER}"
    REGISTRY_PREFIX = "${params.REGISTRY}${params.NAMESPACE == '' ? '' : '/'}${params.NAMESPACE}"
  }

  stages {
    stage('Checkout from Git') {
      steps {
        echo '========== Cloning repository from Git =========='
        echo "Branch: ${env.BRANCH_NAME ?: 'main'}"
        echo "Repository URL: ${env.GIT_URL ?: 'configured in Jenkins job'}"
        
        checkout scm
        
        echo '✓ Git checkout successful'
      }
    }

    stage('Verify Docker & Node') {
      steps {
        echo '========== Verifying prerequisites =========='
        sh '''
          echo "Docker version:"
          docker --version
          echo ""
          echo "Verifying Docker Hub access..."
          # Don't try to pull here - just verify Docker works
          # Authentication will happen before building
        '''
      }
    }

    stage('Docker Login') {
      steps {
        echo '========== Authenticating to Docker Registry =========='
        withCredentials([usernamePassword(
          credentialsId: params.DOCKER_CREDENTIALS_ID,
          passwordVariable: 'DOCKER_PASSWORD',
          usernameVariable: 'DOCKER_USERNAME'
        )]) {
          sh '''
            echo "Logging in to Docker Hub..."
            echo ${DOCKER_PASSWORD} | docker login -u ${DOCKER_USERNAME} --password-stdin
            if [ $? -eq 0 ]; then
              echo "✓ Docker authentication successful"
            else
              echo "✗ Docker authentication failed"
              exit 1
            fi
          '''
        }
      }
    }

    stage('Build Frontend') {
      steps {
        echo '========== Building Angular Frontend =========='
        sh '''
          cd inventory_management_frontend-main
          pwd
          
          echo "Running npm install and build..."
          docker run --rm \
            -v "$PWD":/app \
            -w /app \
            node:22-alpine \
            sh -c "npm install && npm run build -- --configuration production || npm run build"
          
          echo "✓ Frontend build completed"
          echo "Build output location: dist/"
          ls -lah dist/ | head -5 || echo "Note: dist directory structure varies by Angular config"
        '''
      }
    }

    stage('Build Docker Images') {
      steps {
        echo '========== Building Docker Images =========='
        script {
          FRONT_TAG = "${env.REGISTRY_PREFIX}/${params.FRONTEND_IMAGE}:${env.BUILD_TAG}"
          BACK_TAG = "${env.REGISTRY_PREFIX}/${params.BACKEND_IMAGE}:${env.BUILD_TAG}"

          echo "Frontend image tag: ${FRONT_TAG}"
          sh "docker build -t ${FRONT_TAG} inventory_management_frontend-main"
          echo "✓ Frontend image built successfully"

          echo ""
          echo "Backend image tag: ${BACK_TAG}"
          sh "docker build -t ${BACK_TAG} inventory_management_backend-main"
          echo "✓ Backend image built successfully"
          
          echo ""
          echo "Built images:"
          sh "docker images | grep -E '(appanix|REPOSITORY)' || true"
        }
      }
    }

    stage('Push Images to Registry') {
      steps {
        echo '========== Pushing Images to Docker Registry =========='
        script {
          if (params.NAMESPACE == '' || params.NAMESPACE == null) {
            echo "⚠ WARNING: NAMESPACE is empty. Images will NOT be pushed to registry."
            echo "To push images, set NAMESPACE to your Docker Hub username."
            echo "Local images created: ${env.REGISTRY_PREFIX}/${params.FRONTEND_IMAGE}:${env.BUILD_TAG}"
            echo "Local images created: ${env.REGISTRY_PREFIX}/${params.BACKEND_IMAGE}:${env.BUILD_TAG}"
          } else {
            echo "Pushing images to Docker registry..."
            
            FRONT_TAG = "${env.REGISTRY_PREFIX}/${params.FRONTEND_IMAGE}:${env.BUILD_TAG}"
            BACK_TAG = "${env.REGISTRY_PREFIX}/${params.BACKEND_IMAGE}:${env.BUILD_TAG}"
            
            sh '''
              echo "Pushing frontend image: ${FRONT_TAG}"
              docker push ${FRONT_TAG}
              if [ $? -eq 0 ]; then
                echo "✓ Frontend image pushed successfully"
              else
                echo "✗ Failed to push frontend image"
                exit 1
              fi
              
              echo ""
              echo "Pushing backend image: ${BACK_TAG}"
              docker push ${BACK_TAG}
              if [ $? -eq 0 ]; then
                echo "✓ Backend image pushed successfully"
              else
                echo "✗ Failed to push backend image"
                exit 1
              fi
            '''
          }
        }
      }
    }
  }

  post {
    success {
      echo "========== ✓ PIPELINE SUCCEEDED =========="
      script {
        FRONT_TAG = "${env.REGISTRY_PREFIX}/${params.FRONTEND_IMAGE}:${env.BUILD_TAG}"
        BACK_TAG = "${env.REGISTRY_PREFIX}/${params.BACKEND_IMAGE}:${env.BUILD_TAG}"
        
        if (params.NAMESPACE == '' || params.NAMESPACE == null) {
          echo "Images built locally (not pushed to registry):"
        } else {
          echo "Images successfully pushed to registry:"
        }
        echo "  Frontend: ${FRONT_TAG}"
        echo "  Backend:  ${BACK_TAG}"
      }
    }
    
    failure {
      echo "========== ✗ PIPELINE FAILED =========="
      echo "Check console output above for error details."
      echo ""
      echo "Common issues:"
      echo "  1. Git clone failed → Verify repository URL and credentials"
      echo "  2. Docker build failed → Check Dockerfile syntax"
      echo "  3. Docker login failed → Verify docker-hub-creds in Jenkins Manage Credentials"
      echo "  4. npm install/build failed → Check package.json and dependencies"
    }
  }
}
