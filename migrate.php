<?php
/*
  Descrição do Desafio:
    Você precisa realizar uma migração dos dados fictícios que estão na pasta <dados_sistema_legado> para a base da clínica fictícia MedicalChallenge.
    Para isso, você precisa:
      1. Instalar o MariaDB na sua máquina. Dica: Você pode utilizar Docker para isso;
      2. Restaurar o banco da clínica fictícia Medical Challenge: arquivo <medical_challenge_schema>;
      3. Migrar os dados do sistema legado fictício que estão na pasta <dados_sistema_legado>:
        a) Dica: você pode criar uma função para importar os arquivos do formato CSV para uma tabela em um banco temporário no seu MariaDB.
      4. Gerar um dump dos dados já migrados para o banco da clínica fictícia Medical Challenge.
*/

// Importação de Bibliotecas:
include "./lib.php";

// Conexão com o banco da clínica fictícia:
$connMedical = mysqli_connect("db", "root", "root", "MedicalChallenge")
  or die("Não foi possível conectar os servidor MySQL: MedicalChallenge\n");

// Conexão com o banco temporário:
$connTemp = mysqli_connect("mariadb", "root", "root", "MedicalChallenge")
  or die("Não foi possível conectar os servidor MySQL: temporary_database\n");

// Informações de Inicio da Migração:
echo "Início da Migração: " . dateNow() . ".\n\n";

// Caminho para os arquivos CSV contendo os dados do sistema legado
$agendamendosCsvFilePath = "dados_sistema_legado/20210512_agendamentos.csv";
$pacientesCsvFilePath = "dados_sistema_legado/20210512_pacientes.csv";

// Verificação se a extenção dos arquivos contendo os dados do sistema legado são do tipo CSV
checkExtension($agendamendosCsvFilePath);
checkExtension($pacientesCsvFilePath);

// Lê os dados dos arquivos .csv contendo os dados do sistema legado
$agendamentosCsvData = fopen($agendamendosCsvFilePath, "r");
$pacientesCsvData = fopen($pacientesCsvFilePath, "r");

//Migração de dados dos pacientes
pacientesMigration($pacientesCsvData, $connMedical);

//Migração dos dados de agendamento
agendamentosMigration($agendamentosCsvData, $connMedical);

//Fecha os arquivos CSV
fclose($agendamentosCsvData);
fclose($pacientesCsvData);

// Encerrando as conexões:
$connMedical->close();
$connTemp->close();

// Informações de Fim da Migração:
echo "Fim da Migração: " . dateNow() . ".\n";