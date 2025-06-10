<?php
// calculator.php
require 'config.php';
require 'utils.php';
check_auth(); // Только зарегистрированные пользователи видят эту страницу
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1.0" />
  <title>Калькулятор энергозатрат VM</title>
  <!-- Подключаем Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body { font-family: Arial, sans-serif; max-width: 900px; margin: 0 auto; padding: 20px; }
    a.back-btn { display: inline-block; margin-bottom: 15px; color: #007bff; text-decoration: none; }
    a.back-btn:hover { text-decoration: underline; }
    h1 { text-align: center; margin-bottom: 20px; }
    .tabs { display: flex; border-bottom: 2px solid #ccc; margin-bottom: 20px; overflow-x: auto; }
    .tabs li { list-style: none; padding: 10px 20px; cursor: pointer; white-space: nowrap; }
    .tabs li.active { border-bottom: 3px solid #007bff; color: #007bff; font-weight: bold; }
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(200px,1fr)); gap:15px; margin-bottom:15px; }
    label { display: block; margin-bottom: 5px; font-weight: bold; }
    input, select, button { width:100%; padding:8px; box-sizing:border-box; }
    button { background:#007bff; color:#fff; border:none; cursor:pointer; }
    button:hover { background:#0056b3; }
    .result { background:#f0f8ff; padding:15px; border-left:4px solid #007bff; margin:15px 0; font-weight:bold; }
    .chart-container { position: relative; height:300px; overflow-x:auto; margin-top:20px; }
    canvas { display:block; height:100% !important; }
    .radio-group { display:flex; gap:10px; align-items:center; }
    table { width:100%; border-collapse:collapse; margin-top:15px; }
    th, td { border:1px solid #ccc; padding:6px; text-align:center; }
  </style>
</head>
<body>

  <!-- Кнопка "Назад" на панель -->
  <a href="dashboard.php" class="back-btn">← Назад</a>
  <h1>Калькулятор энергозатрат VM</h1>

  <!-- Вкладки -->
  <ul class="tabs">
    <li data-tab="calc"    class="active">Калькулятор</li>
    <li data-tab="optimize">Оптимизация</li>
    <li data-tab="co2"      >CO₂</li>
    <li data-tab="live"     >Live-метрики</li>
    <li data-tab="history"  >История</li>
  </ul>

  <!-- === 1. Вкладка: Калькулятор === -->
  <div id="calc" class="tab-content active">
    <div class="form-grid">
      <div>
        <label for="vm-type">Тип VM:</label>
        <select id="vm-type">
          <option value="t3.micro">AWS t3.micro</option>
          <option value="t3.small">AWS t3.small</option>
          <option value="t3.medium" selected>AWS t3.medium</option>
          <option value="t3.large">AWS t3.large</option>
          <option value="m5.large">AWS m5.large</option>
          <option value="c5.large">AWS c5.large</option>
          <option value="B1s">Azure B1s</option>
          <option value="B2s">Azure B2s</option>
          <option value="D2s_v3">Azure D2s_v3</option>
          <option value="F2s_v2">Azure F2s_v2</option>
          <option value="e2-micro">GCP e2-micro</option>
          <option value="e2-small">GCP e2-small</option>
          <option value="e2-medium">GCP e2-medium</option>
          <option value="n1-standard-1">GCP n1-standard-1</option>
          <option value="n2-standard-2">GCP n2-standard-2</option>
        </select>
      </div>
      <div>
        <label for="count">Экземпляров:</label>
        <input id="count" type="number" min="1" value="1" />
      </div>
      <div>
        <label for="hours">Часы работы:</label>
        <input id="hours" type="number" min="0" value="24" />
      </div>
      <div>
        <label for="load">Загрузка CPU %:</label>
        <input id="load" type="number" min="0" max="100" value="50" />
      </div>
    </div>
    <button id="calc-btn">Рассчитать</button>
    <div id="output" class="result" style="display:none;"></div>
    <div class="chart-container">
      <canvas id="chart"></canvas>
    </div>
  </div>

  <!-- === 2. Вкладка: Оптимизация === -->
  <div id="optimize" class="tab-content">
    <div class="form-grid">
      <div>
        <label>Критерий:</label>
        <div class="radio-group">
          <label><input type="radio" name="criterion" value="energy" checked> Энергия</label>
          <label><input type="radio" name="criterion" value="cost"> Стоимость</label>
          <label><input type="radio" name="criterion" value="balanced"> Сбаланс.</label>
        </div>
      </div>
      <div>
        <label for="opt-hours">Часы работы:</label>
        <input id="opt-hours" type="number" min="0" value="24" />
      </div>
      <div>
        <label for="opt-load">Загрузка %:</label>
        <input id="opt-load" type="number" min="0" max="100" value="50" />
      </div>
    </div>
    <button id="opt-btn">Оптимизировать</button>
    <div id="opt-result" class="result" style="display:none;"></div>
    <div class="chart-container">
      <canvas id="opt-chart"></canvas>
    </div>
  </div>

  <!-- === 3. Вкладка: CO₂ === -->
  <div id="co2" class="tab-content">
    <div class="form-grid">
      <div>
        <label for="region">Регион (gCO₂/kWh):</label>
        <select id="region">
          <option value="830.4" selected>Kazakhstan</option>
          <option value="250">EU</option>
          <option value="400">US</option>
          <option value="700">RU</option>
        </select>
      </div>
      <div>
        <label for="pue">PUE ЦОД:</label>
        <input id="pue" type="number" min="1" step="0.1" value="1.2" />
      </div>
      <div>
        <label for="local-cost">Цена эл-энергии $/kWh:</label>
        <input id="local-cost" type="number" min="0" step="0.01" value="0.1" />
      </div>
    </div>
    <button id="co2-btn">Рассчитать CO₂</button>
    <div id="co2-result" class="result" style="display:none;"></div>
  </div>

  <!-- === 4. Вкладка: Live-метрики === -->
  <div id="live" class="tab-content">
    <p>График Host Power и VM Power (последние 20 сек):</p>
    <div class="chart-container">
      <canvas id="live-chart"></canvas>
    </div>
  </div>

  <!-- === 5. Вкладка: История === -->
  <div id="history" class="tab-content">
    <button id="clear-btn" style="background:#e74c3c;color:#fff;">Очистить историю</button>
    <div class="chart-container">
      <canvas id="history-chart"></canvas>
    </div>
    <table id="history-table">
      <thead><tr><th>#</th><th>Дата</th><th>VM</th><th>Часы</th><th>%</th><th>kWh</th><th>$</th></tr></thead>
      <tbody></tbody>
    </table>
  </div>

  <!-- ============ Общий JavaScript ============ -->
  <script>
  document.addEventListener('DOMContentLoaded', () => {
    // Словарь VM: мощность (Вт при 100% CPU) и цена $/час
    const VM = {
      "t3.micro":      { power:20, cost:0.0104 },
      "t3.small":      { power:25, cost:0.0208 },
      "t3.medium":     { power:30, cost:0.0416 },
      "t3.large":      { power:40, cost:0.0832 },
      "m5.large":      { power:60, cost:0.096  },
      "c5.large":      { power:70, cost:0.085  },
      "B1s":           { power:15, cost:0.018  },
      "B2s":           { power:28, cost:0.0416 },
      "D2s_v3":        { power:50, cost:0.096  },
      "F2s_v2":        { power:40, cost:0.085  },
      "e2-micro":      { power:15, cost:0.0076 },
      "e2-small":      { power:20, cost:0.016  },
      "e2-medium":     { power:30, cost:0.0335 },
      "n1-standard-1": { power:40, cost:0.0475 },
      "n2-standard-2": { power:60, cost:0.095  }
    };

    // Переключение вкладок
    const tabsLi      = document.querySelectorAll('.tabs li');
    const tabContents = document.querySelectorAll('.tab-content');
    function saveRecord(r) {
      const arr = JSON.parse(localStorage.getItem('history')||'[]');
      arr.push(r);
      localStorage.setItem('history', JSON.stringify(arr));
    }
    function renderHistory() {
      const arr = JSON.parse(localStorage.getItem('history')||'[]');
      const tbody = document.querySelector('#history-table tbody');
      tbody.innerHTML = '';
      arr.forEach((r,i) => {
        tbody.insertAdjacentHTML('beforeend', `
          <tr>
            <td>${i+1}</td>
            <td>${r.date}</td>
            <td>${r.type}</td>
            <td>${r.hrs}</td>
            <td>${(r.load*100).toFixed(0)}%</td>
            <td>${r.energy.toFixed(2)}</td>
            <td>${r.money.toFixed(2)}</td>
          </tr>`);
      });
      if (window.historyChart) window.historyChart.destroy();
      window.historyChart = new Chart(document.getElementById('history-chart').getContext('2d'), {
        type:'line',
        data:{
          labels: arr.map((_,i)=>i+1),
          datasets:[
            { label:'kWh', data: arr.map(r=>r.energy), fill:false },
            { label:'$',   data: arr.map(r=>r.money),  fill:false }
          ]
        },
        options:{ responsive:true }
      });
    }

    tabsLi.forEach(li => {
      li.addEventListener('click', () => {
        tabsLi.forEach(x=>x.classList.remove('active'));
        tabContents.forEach(x=>x.classList.remove('active'));
        li.classList.add('active');
        document.getElementById(li.dataset.tab).classList.add('active');
        if (li.dataset.tab === 'history') renderHistory();
      });
    });

    // ========== 1. Калькулятор ==========
    (function(){
      const vmType = document.getElementById('vm-type');
      const countEl = document.getElementById('count');
      const hoursEl = document.getElementById('hours');
      const loadEl  = document.getElementById('load');
      const calcBtn = document.getElementById('calc-btn');
      const output  = document.getElementById('output');
      let calcChart;
      calcBtn.addEventListener('click', () => {
        const type = vmType.value, cnt = +countEl.value, hrs = +hoursEl.value, ld = +loadEl.value/100;
        if (!VM[type]||cnt<1||hrs<0||ld<0||ld>1) return alert('Проверьте ввод');
        const {power,cost} = VM[type];
        // kWh = (W * count * hours * load) / 1000
        const energy = power*cnt*hrs*ld/1000;
        const money  = cost *cnt*hrs;
        output.style.display='block';
        output.textContent = `Потребление: ${energy.toFixed(2)} kWh, Стоимость: $${money.toFixed(2)}`;
        if (calcChart) calcChart.destroy();
        calcChart = new Chart(document.getElementById('chart').getContext('2d'), {
          data:{
            labels:['Энергия','Стоимость'],
            datasets:[
              {type:'bar',  label:'kWh', data:[energy,0], yAxisID:'y'},
              {type:'line', label:'$',   data:[0,money],yAxisID:'y1'}
            ]
          },
          options:{
            responsive:true,
            scales:{
              y: {beginAtZero:true, title:{display:true,text:'kWh'}},
              y1:{beginAtZero:true, position:'right',title:{display:true,text:'$'}}
            }
          }
        });
        // Сохраняем в историю (для вкладки "История")
        saveRecord({ date:new Date().toLocaleString(), type, hrs, load:ld, energy, money });
        // Сохраняем в localStorage тип VM (для Live)
        localStorage.setItem('live_vm_type', type);
      });
    })();

    // ========== 2. Оптимизация ==========
    (function(){
      const optHours     = document.getElementById('opt-hours');
      const optLoad      = document.getElementById('opt-load');
      const optBtn       = document.getElementById('opt-btn');
      const optResult    = document.getElementById('opt-result');
      const optChartElem = document.getElementById('opt-chart');
      let optChart;
      optBtn.addEventListener('click', () => {
        const hrs = +optHours.value, ld=+optLoad.value/100;
        const crit = document.querySelector('input[name="criterion"]:checked').value;
        let items = Object.entries(VM).map(([k,v])=>({
          type:k, energy:v.power*hrs*ld/1000, money:v.cost*hrs
        }));
        if (crit==='energy') items.sort((a,b)=>a.energy-b.energy);
        else if (crit==='cost') items.sort((a,b)=>a.money-b.money);
        else {
          const α=0.5;
          items.sort((a,b)=>(α*(a.energy-b.energy)+(1-α)*(a.money-b.money)));
        }
        const best=items[0];
        optResult.style.display='block';
        optResult.textContent=`Рекомендуемый тип: ${best.type}`;
        if (optChart) optChart.destroy();
        optChart = new Chart(optChartElem.getContext('2d'), {
          type:'bar',
          data:{ labels:items.map(i=>i.type),
                 datasets:[
                   {label:'kWh',data:items.map(i=>i.energy)},
                   {label:'$',  data:items.map(i=>i.money)}
                 ]},
          options:{responsive:true}
        });
      });
    })();

    // ========== 3. CO₂ ==========
    (function(){
      const regionEl=document.getElementById('region'),
            pueEl=document.getElementById('pue'),
            localCost=document.getElementById('local-cost'),
            co2Btn=document.getElementById('co2-btn'),
            co2Res=document.getElementById('co2-result');
      co2Btn.addEventListener('click', () => {
        const arr=JSON.parse(localStorage.getItem('history')||'[]');
        if (!arr.length) return alert('Сначала расчитайте');
        const last=arr[arr.length-1],
              gpk=+regionEl.value,
              pue=+pueEl.value,
              eCost=+localCost.value,
              effE=last.energy*pue,
              kgCO2=effE*gpk/1000,
              costL=effE*eCost;
        co2Res.style.display='block';
        co2Res.innerHTML=`
          Эфф. потребление: ${effE.toFixed(2)} kWh<br>
          Выбросы CO₂: ${kgCO2.toFixed(2)} кг<br>
          Стоимость эл-энергии: $${costL.toFixed(2)}
        `;
      });
    })();

    // ========== 4. Live-метрики (последние 20 сек, динамическое сравнение) ==========
    (function initLive() {
      const MAX_POINTS = 20;
      const ctx = document.getElementById('live-chart').getContext('2d');

      // Читаем из localStorage только тип VM (на каком инстансе сравниваем)
      const vmTypeSelected = localStorage.getItem('live_vm_type');
      if (!vmTypeSelected) {
        alert('Сначала во вкладке "Калькулятор" нажмите "Рассчитать", чтобы выбрать VM.');
      }

      // 1) Инициализируем метки: ["-19s", "-18s", …, "-1s", "0s"]
      let labels = [];
      for (let i = MAX_POINTS - 1; i >= 0; i--) {
        labels.push(`-${i}s`);
      }

      // 2) Данные (host и vm) — по 20 точек
      let hostData = new Array(MAX_POINTS).fill(0);
      let vmData   = new Array(MAX_POINTS).fill(0);

      // 3) Создаём Chart.js
      const liveChart = new Chart(ctx, {
        type: 'line',
        data: {
          labels: labels,
          datasets: [
            {
              label: 'Host Power (Вт)',
              data: hostData,
              borderColor: 'rgba(54, 162, 235, 1)',
              backgroundColor: 'rgba(54, 162, 235, 0.2)',
              tension: 0.3,
              fill: false,
              pointRadius: 2,
              borderWidth: 2,
              yAxisID: 'y'
            },
            {
              label: `VM Power (${vmTypeSelected})`,
              data: vmData,
              borderColor: 'rgba(255, 159, 64, 1)',
              backgroundColor: 'rgba(255, 159, 64, 0.2)',
              tension: 0.3,
              fill: false,
              pointRadius: 2,
              borderWidth: 2,
              yAxisID: 'y'
            }
          ]
        },
        options: {
          responsive: true,
          scales: {
            y: {
              beginAtZero: true,
              // Если ваш TDP хоста может быть >100 Вт, тогда лучше убрать max или поставить HOST_TDP+10
              max: 100,  
              title: { display: true, text: 'Мощность (Вт)' }
            },
            x: {
              title: { display: true, text: 'Время (секунды назад)' },
              ticks: {
                autoSkip: false,
                maxRotation: 0,
                minRotation: 0
              }
            }
          },
          plugins: {
            legend: { position: 'top' }
          }
        }
      });

      // 4) Функция для запроса metrics.php и обновления графика
      function fetchAndUpdateMetrics() {
        fetch('metrics.php')
          .then(res => res.json())
          .then(json => {
            // json.cpu → % загрузка хоста
            // json.host_power → уже рассчитанная мощность хоста в ваттах
            const hostPwr = parseFloat(json.host_power);
            // Динамически рассчитываем vmPwr согласно той же % загрузке:
            let vmPwr = 0;
            if (vmTypeSelected && VM[vmTypeSelected]) {
              vmPwr = VM[vmTypeSelected].power * (json.cpu / 100.0);
              vmPwr = parseFloat(vmPwr.toFixed(2));
            }

            // Сдвигаем старые точки
            hostData.shift();
            vmData.shift();
            // Добавляем новые значения в конец
            hostData.push(hostPwr);
            vmData.push(vmPwr);

            // Обновляем метки "–19s…–1s, 0s"
            labels.shift();
            labels.push('0s');
            for (let i = MAX_POINTS - 1; i >= 0; i--) {
              labels[MAX_POINTS - 1 - i] = `-${i}s`;
            }

            // Перерисовываем график
            liveChart.update();
          })
          .catch(err => console.error('Ошибка при fetch(metrics.php):', err));
      }

      // Запуск сразу (чтобы не ждать 1 секунду) и далее через интервал
      fetchAndUpdateMetrics();
      setInterval(fetchAndUpdateMetrics, 1000);
    })();

    // ========== 5. История: очистка и рендер ==========
    document.getElementById('clear-btn').addEventListener('click', () => {
      localStorage.removeItem('history');
      renderHistory();
    });
    renderHistory();

  }); // конец DOMContentLoaded
  </script>

</body>
</html>
