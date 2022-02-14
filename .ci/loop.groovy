#!/usr/bin/env groovy

@Library('apm@current') _

pipeline {
  agent none
  environment {
    REPO = 'apm-agent-php'
    BASE_DIR = "src/go.elastic.co/apm/${env.REPO}"
    NOTIFY_TO = 'build-apm+apm-agent-php@elastic.co'
    LOOPS = "${params.LOOPS}"
    GITHUB_CHECK_NAME = 'apm-loop'
  }
  options {
    buildDiscarder(logRotator(numToKeepStr: '20', artifactNumToKeepStr: '20', daysToKeepStr: '30'))
    timestamps()
    ansiColor('xterm')
    disableResume()
    durabilityHint('PERFORMANCE_OPTIMIZED')
    rateLimitBuilds(throttle: [count: 60, durationName: 'hour', userBoost: true])
    quietPeriod(10)
    timeout(time: 7, unit: 'HOURS')
  }
  triggers {
    issueCommentTrigger('(?i).*jenkins\\W+run\\W+(?:the\\W+)?loop\\W+tests(?:\\W+please)?.*')
    // disable upstream trigger on a PR basis
    upstream("apm-agent-php/apm-agent-php-mbp/${ env.JOB_BASE_NAME.startsWith('PR-') ? 'none' : env.JOB_BASE_NAME }")
    cron('@midnight')
  }
  parameters {
    string(name: 'LOOPS', defaultValue: '50', description: 'How many test loops?')
  }
  stages {
    stage('Filter build') {
      agent { label 'linux && docker && ubuntu-18.04 && immutable' }
      when {
        beforeAgent true
        anyOf {
          allOf {
            branch 'main'
            triggeredBy cause: 'TimerTrigger'
          }
          triggeredBy cause: "IssueCommentCause"
          expression {
            def ret = isUserTrigger() || isUpstreamTrigger()
            if(!ret){
              currentBuild.result = 'NOT_BUILT'
              currentBuild.description = "The build has been skipped"
              currentBuild.displayName = "#${BUILD_NUMBER}-(Skipped)"
              echo("the build has been skipped due the trigger is a branch scan and the allow ones are manual, GitHub comment, and upstream job")
            }
            return ret
          }
        }
      }
      stages {
        stage('Checkout') {
          steps {
            githubNotify(context: "${env.GITHUB_CHECK_NAME}", description: "${env.GITHUB_CHECK_NAME} ...", status: 'PENDING', targetUrl: "${env.BUILD_URL}")
            pipelineManager([ cancelPreviousRunningBuilds: [ when: 'PR' ] ])
            deleteDir()
            gitCheckout(basedir: "${BASE_DIR}", githubNotifyFirstTimeContributor: true)
            stash allowEmpty: true, name: 'source', useDefaultExcludes: false
          }
        }
        stage('BuildAndTest') {
          failFast false
          matrix {
            agent { label 'linux && docker && ubuntu-18.04 && immutable' }
            options { skipDefaultCheckout() }
            axes {
              axis {
                name 'PHP_VERSION'
                values '7.2', '7.3', '7.4', '8.0'
              }
              axis {
                name 'DOCKERFILE'
                values 'Dockerfile', 'Dockerfile.alpine'
              }
            }
            stages {
              stage('Build') {
                steps {
                  deleteDir()
                  unstash 'source'
                  dir("${BASE_DIR}"){
                    // When running in the CI with multiple parallel stages
                    // the access could be considered as a DDOS attack.
                    retryWithSleep(retries: 3, seconds: 5, backoff: true) {
                      sh script: "PHP_VERSION=${PHP_VERSION} DOCKERFILE=${DOCKERFILE} make -f .ci/Makefile prepare", label: 'prepare docker image'
                    }
                    sh script: "PHP_VERSION=${PHP_VERSION} DOCKERFILE=${DOCKERFILE} make -f .ci/Makefile build", label: 'build'
                  }
                }
              }
              stage('Tests loop') {
                steps {
                  dir("${BASE_DIR}"){
                    sh script: "LOOPS=${LOOPS} PHP_VERSION=${PHP_VERSION} DOCKERFILE=${DOCKERFILE} make -f .ci/Makefile loop", label: 'test'
                  }
                }
                post {
                  always {
                    junit(allowEmptyResults: true, keepLongStdio: true, testResults: "${BASE_DIR}/build/**/junit*.xml")
                    archiveArtifacts(allowEmptyArchive: true, artifacts: "${BASE_DIR}/build/**/*.txt")
                  }
                }
              }
            }
          }
        }
      }
      post {
        success {
          githubNotify(context: "${env.GITHUB_CHECK_NAME}", description: "${env.GITHUB_CHECK_NAME} passed", status: 'SUCCESS', targetUrl: "${env.BUILD_URL}")
        }
        unsuccessful {
          githubNotify(context: "${env.GITHUB_CHECK_NAME}", description: "${env.GITHUB_CHECK_NAME} failed", status: 'FAILURE', targetUrl: "${env.BUILD_URL}")
        }
        cleanup {
          // PR comment is not needed with this pipeline
          notifyBuildResult(prComment: false)
        }
      }
    }
  }
}
