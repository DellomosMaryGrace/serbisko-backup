from flask import Flask, request, jsonify
from flask_cors import CORS
import threading, requests
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.service import Service
from webdriver_manager.chrome import ChromeDriverManager

app = Flask(__name__)
CORS(app)

# CONFIG
LIS_URL = "https://learner-information-system-dashboard-540972607515.us-west1.run.app/"
CREDS = ("depedsample@gmail.com", "deped123")

def run_check(lrn, webhook, scan_id):
    result = "NEITHER"
    try:
        options = webdriver.ChromeOptions()
        options.add_argument("--headless=new")
        driver = webdriver.Chrome(service=Service(ChromeDriverManager().install()), options=options)
        
        driver.get(LIS_URL)
        # Login
        driver.find_element(By.XPATH, "//input[@type='email']").send_keys(CREDS[0])
        driver.find_element(By.XPATH, "//input[@type='password']").send_keys(CREDS[1])
        driver.find_element(By.XPATH, "//button[@type='submit']").click()
        
        # Navigate & Search (Simplified XPaths for brevity)
        driver.implicitly_wait(5)
        driver.find_element(By.XPATH, "//*[contains(text(),'Masterlist')]").click()
        driver.find_element(By.XPATH, "//*[contains(text(),'Enrol Learner')]").click()
        driver.find_element(By.XPATH, "//*[contains(text(),'Proceed')]").click()
        
        inp = driver.find_element(By.XPATH, "//input[@placeholder='Search LRN' or contains(@aria-label,'Search')]")
        inp.send_keys(lrn)
        inp.find_element(By.XPATH, "./following-sibling::button").click()
        
        # Check Result
        driver.find_element(By.XPATH, "//*[contains(text(),'Preview')]").click()
        text = driver.find_element(By.XPATH, "//div[contains(@class,'text-sm')]").text
        
        if "Grade 10" in text: result = "GRADE_10"
        elif "Grade 11" in text: result = "GRADE_11"
        
        driver.quit()
    except:
        pass

    # Callback to Laravel
    requests.post(webhook, json={'scan_id': scan_id, 'result': result})

@app.route('/verify', methods=['POST'])
def verify():
    data = request.json
    threading.Thread(target=run_check, args=(data['lrn'], data['webhook_url'], data['scan_id'])).start()
    return jsonify({'status': 'started'})

if __name__ == '__main__':
    app.run(port=5001)